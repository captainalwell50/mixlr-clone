#include "ndi_plugin.h"

#include <flutter/encodable_value.h>
#include <flutter/method_channel.h>
#include <flutter/standard_method_codec.h>

#include <algorithm>
#include <atomic>
#include <cstdint>
#include <mutex>
#include <string>
#include <thread>
#include <vector>

#include <windows.h>
#include <wincodec.h>

#include "ndi_sdk_shim.h"

#pragma comment(lib, "windowscodecs.lib")
#pragma comment(lib, "ole32.lib")

namespace {

using InitFn = bool (*)();
using FindCreateFn = void* (*)(const SmlNdiFindCreate*);
using FindDestroyFn = void (*)(void*);
using FindWaitFn = bool (*)(void*, uint32_t);
using FindSourcesFn = const SmlNdiSource* (*)(void*, uint32_t*);
using RecvCreateFn = void* (*)(const SmlNdiRecvCreate*);
using RecvDestroyFn = void (*)(void*);
using RecvCaptureFn = int32_t (*)(void*, SmlNdiVideoFrame*, void*, void*, uint32_t);
using RecvFreeVideoFn = void (*)(void*, const SmlNdiVideoFrame*);

std::vector<uint8_t> EncodeJpeg(const uint8_t* bgra, int width, int height, int stride) {
  std::vector<uint8_t> out;
  IWICImagingFactory* factory = nullptr;
  if (FAILED(CoCreateInstance(CLSID_WICImagingFactory, nullptr, CLSCTX_INPROC_SERVER,
                              IID_PPV_ARGS(&factory)))) {
    return out;
  }
  IWICBitmap* bitmap = nullptr;
  HRESULT hr = factory->CreateBitmapFromMemory(
      width, height, GUID_WICPixelFormat32bppBGRA, stride, stride * height,
      const_cast<BYTE*>(bgra), &bitmap);
  if (FAILED(hr) || !bitmap) {
    factory->Release();
    return out;
  }
  IStream* stream = nullptr;
  if (FAILED(CreateStreamOnHGlobal(nullptr, TRUE, &stream))) {
    bitmap->Release();
    factory->Release();
    return out;
  }
  IWICBitmapEncoder* encoder = nullptr;
  hr = factory->CreateEncoder(GUID_ContainerFormatJpeg, nullptr, &encoder);
  if (SUCCEEDED(hr)) {
    encoder->Initialize(stream, WICBitmapEncoderNoCache);
    IWICBitmapFrameEncode* frame = nullptr;
    IPropertyBag2* props = nullptr;
    encoder->CreateNewFrame(&frame, &props);
    if (frame) {
      PROPBAG2 option{};
      WCHAR qualityName[] = L"ImageQuality";
      option.pstrName = qualityName;
      VARIANT value;
      VariantInit(&value);
      value.vt = VT_R4;
      value.fltVal = 0.72f;
      if (props) {
        props->Write(1, &option, &value);
      }
      VariantClear(&value);
      frame->Initialize(props);
      frame->SetSize(width, height);
      WICPixelFormatGUID format = GUID_WICPixelFormat24bppBGR;
      frame->SetPixelFormat(&format);
      frame->WriteSource(bitmap, nullptr);
      frame->Commit();
      frame->Release();
    }
    if (props) {
      props->Release();
    }
    encoder->Commit();
    encoder->Release();
  }
  STATSTG stat{};
  stream->Stat(&stat, STATFLAG_NONAME);
  HGLOBAL hg = nullptr;
  if (SUCCEEDED(GetHGlobalFromStream(stream, &hg)) && hg) {
    SIZE_T size = GlobalSize(hg);
    void* locked = GlobalLock(hg);
    if (locked && size > 0) {
      out.assign(static_cast<uint8_t*>(locked), static_cast<uint8_t*>(locked) + size);
    }
    if (locked) {
      GlobalUnlock(hg);
    }
  }
  stream->Release();
  bitmap->Release();
  factory->Release();
  return out;
}

}  // namespace

class NdiPluginHost::Impl {
 public:
  void Register(flutter::BinaryMessenger* messenger, HWND hwnd) {
    hwnd_ = hwnd;
    channel_ = std::make_unique<flutter::MethodChannel<flutter::EncodableValue>>(
        messenger, "soundmix/ndi", &flutter::StandardMethodCodec::GetInstance());
    channel_->SetMethodCallHandler(
        [this](const flutter::MethodCall<flutter::EncodableValue>& call,
               std::unique_ptr<flutter::MethodResult<flutter::EncodableValue>> result) {
          Handle(call, std::move(result));
        });
    CoInitializeEx(nullptr, COINIT_MULTITHREADED);
  }

  bool HandleMessage(UINT message) {
    if (message != NdiPluginHost::kFrameMessage) {
      return false;
    }
    EmitPending();
    return true;
  }

  void Shutdown() {
    capturing_ = false;
    if (capture_.joinable()) {
      capture_.join();
    }
    if (recv_ && fnRecvDestroy_) {
      fnRecvDestroy_(recv_);
      recv_ = nullptr;
    }
    if (find_ && fnFindDestroy_) {
      fnFindDestroy_(find_);
      find_ = nullptr;
    }
    if (dll_) {
      FreeLibrary(dll_);
      dll_ = nullptr;
    }
  }

 private:
  void Handle(const flutter::MethodCall<flutter::EncodableValue>& call,
              std::unique_ptr<flutter::MethodResult<flutter::EncodableValue>> result) {
    const auto& method = call.method_name();
    if (method == "status") {
      result->Success(StatusMap());
      return;
    }
    if (method == "listSources") {
      result->Success(ListSources());
      return;
    }
    if (method == "connect") {
      std::string name;
      if (const auto* map = std::get_if<flutter::EncodableMap>(call.arguments())) {
        auto it = map->find(flutter::EncodableValue("name"));
        if (it != map->end()) {
          if (const auto* s = std::get_if<std::string>(&it->second)) {
            name = *s;
          }
        }
      } else if (const auto* s = std::get_if<std::string>(call.arguments())) {
        name = *s;
      }
      if (name.empty()) {
        result->Error("bad_args", "NDI source name required");
        return;
      }
      std::string err;
      if (!Connect(name, &err)) {
        result->Error("ndi_connect", err);
        return;
      }
      flutter::EncodableMap ok;
      ok[flutter::EncodableValue("ok")] = flutter::EncodableValue(true);
      ok[flutter::EncodableValue("source")] = flutter::EncodableValue(name);
      result->Success(ok);
      return;
    }
    if (method == "disconnect") {
      Disconnect();
      flutter::EncodableMap ok;
      ok[flutter::EncodableValue("ok")] = flutter::EncodableValue(true);
      result->Success(ok);
      return;
    }
    result->NotImplemented();
  }

  flutter::EncodableMap StatusMap() {
    bool loaded = EnsureLib();
    flutter::EncodableMap map;
    map[flutter::EncodableValue("available")] = flutter::EncodableValue(loaded);
    map[flutter::EncodableValue("connected")] = flutter::EncodableValue(capturing_.load());
    map[flutter::EncodableValue("source")] = flutter::EncodableValue(connected_name_);
    map[flutter::EncodableValue("message")] = flutter::EncodableValue(
        loaded ? (capturing_ ? ("Receiving " + connected_name_) : std::string("NDI runtime ready"))
               : std::string("Install NDI Tools / NDI Runtime, then restart Studio."));
    return map;
  }

  flutter::EncodableMap ListSources() {
    flutter::EncodableMap map;
    if (!EnsureLib() || !EnsureFinder()) {
      map[flutter::EncodableValue("available")] = flutter::EncodableValue(false);
      map[flutter::EncodableValue("sources")] = flutter::EncodableList();
      map[flutter::EncodableValue("message")] =
          flutter::EncodableValue("Install NDI Tools / NDI Runtime, then restart Studio.");
      return map;
    }
    fnFindWait_(find_, 400);
    uint32_t count = 0;
    const SmlNdiSource* sources = fnFindSources_(find_, &count);
    flutter::EncodableList rows;
    if (sources) {
      for (uint32_t i = 0; i < count; i++) {
        flutter::EncodableMap row;
        row[flutter::EncodableValue("name")] =
            flutter::EncodableValue(sources[i].p_ndi_name ? sources[i].p_ndi_name : "");
        row[flutter::EncodableValue("url")] =
            flutter::EncodableValue(sources[i].p_url_address ? sources[i].p_url_address : "");
        rows.push_back(row);
      }
    }
    map[flutter::EncodableValue("available")] = flutter::EncodableValue(true);
    map[flutter::EncodableValue("sources")] = rows;
    map[flutter::EncodableValue("message")] = flutter::EncodableValue(
        rows.empty() ? "No NDI sources on this network yet. Enable NDI output in EasyWorship, vMix, or OBS."
                     : "");
    return map;
  }

  bool Connect(const std::string& name, std::string* err) {
    if (!EnsureLib()) {
      *err = "Install NDI Tools / NDI Runtime, then restart Studio.";
      return false;
    }
    Disconnect();
    SmlNdiRecvCreate create{};
    create.source_to_connect_to.p_ndi_name = name.c_str();
    create.color_format = SmlNdiColorBgrxBgra;
    create.bandwidth = SmlNdiBandwidthHighest;
    create.allow_video_fields = false;
    create.p_ndi_recv_name = "Sound Mix Live Studio";
    recv_ = fnRecvCreate_(&create);
    if (!recv_) {
      *err = "Could not connect to that NDI source.";
      return false;
    }
    connected_name_ = name;
    capturing_ = true;
    capture_ = std::thread([this]() { CaptureLoop(); });
    return true;
  }

  void Disconnect() {
    capturing_ = false;
    if (capture_.joinable()) {
      capture_.join();
    }
    if (recv_ && fnRecvDestroy_) {
      fnRecvDestroy_(recv_);
      recv_ = nullptr;
    }
    connected_name_.clear();
    last_hash_ = 0;
  }

  void CaptureLoop() {
    CoInitializeEx(nullptr, COINIT_MULTITHREADED);
    while (capturing_ && recv_) {
      SmlNdiVideoFrame frame{};
      int32_t kind = fnRecvCapture_(recv_, &frame, nullptr, nullptr, 200);
      if (kind == SmlNdiFrameVideo && frame.p_data && frame.xres > 0 && frame.yres > 0) {
        uint64_t hash = 14695981039346656037ull;
        int step_x = (std::max)(1, frame.xres / 24);
        int step_y = (std::max)(1, frame.yres / 16);
        for (int y = 0; y < frame.yres; y += step_y) {
          const uint8_t* row = frame.p_data + y * frame.line_stride_in_bytes;
          for (int x = 0; x < frame.xres; x += step_x) {
            const uint8_t* p = row + x * 4;
            hash ^= uint64_t(p[0]) | (uint64_t(p[1]) << 8) | (uint64_t(p[2]) << 16);
            hash *= 1099511628211ull;
          }
        }
        if (hash != last_hash_) {
          last_hash_ = hash;
          auto jpeg = EncodeJpeg(frame.p_data, frame.xres, frame.yres, frame.line_stride_in_bytes);
          std::string source = connected_name_;
          int width = frame.xres;
          int height = frame.yres;
          fnRecvFreeVideo_(recv_, &frame);
          if (!jpeg.empty()) {
            QueueFrame(std::move(jpeg), source, width, height);
          }
          continue;
        }
        fnRecvFreeVideo_(recv_, &frame);
      } else if (kind == SmlNdiFrameVideo) {
        fnRecvFreeVideo_(recv_, &frame);
      }
    }
    CoUninitialize();
  }

  bool EnsureLib() {
    if (fnInit_) {
      return true;
    }
    const wchar_t* paths[] = {
        L"Processing.NDI.Lib.x64.dll",
        L"C:\\Program Files\\NDI\\NDI 6 Runtime\\v6\\Processing.NDI.Lib.x64.dll",
        L"C:\\Program Files\\NDI\\NDI 5 Runtime\\v5\\Processing.NDI.Lib.x64.dll",
        L"C:\\Program Files\\NDI\\NDI Runtime\\v5\\Processing.NDI.Lib.x64.dll",
    };
    for (const auto* path : paths) {
      dll_ = LoadLibraryW(path);
      if (dll_) {
        break;
      }
    }
    if (!dll_) {
      return false;
    }
    fnInit_ = reinterpret_cast<InitFn>(GetProcAddress(dll_, "NDIlib_initialize"));
    fnFindCreate_ = reinterpret_cast<FindCreateFn>(GetProcAddress(dll_, "NDIlib_find_create_v2"));
    fnFindDestroy_ = reinterpret_cast<FindDestroyFn>(GetProcAddress(dll_, "NDIlib_find_destroy"));
    fnFindWait_ = reinterpret_cast<FindWaitFn>(GetProcAddress(dll_, "NDIlib_find_wait_for_sources"));
    fnFindSources_ = reinterpret_cast<FindSourcesFn>(GetProcAddress(dll_, "NDIlib_find_get_current_sources"));
    fnRecvCreate_ = reinterpret_cast<RecvCreateFn>(GetProcAddress(dll_, "NDIlib_recv_create_v3"));
    fnRecvDestroy_ = reinterpret_cast<RecvDestroyFn>(GetProcAddress(dll_, "NDIlib_recv_destroy"));
    fnRecvCapture_ = reinterpret_cast<RecvCaptureFn>(GetProcAddress(dll_, "NDIlib_recv_capture_v2"));
    fnRecvFreeVideo_ = reinterpret_cast<RecvFreeVideoFn>(GetProcAddress(dll_, "NDIlib_recv_free_video_v2"));
    if (!fnInit_ || !fnFindCreate_ || !fnRecvCreate_ || !fnRecvCapture_) {
      return false;
    }
    return fnInit_();
  }

  bool EnsureFinder() {
    if (find_) {
      return true;
    }
    SmlNdiFindCreate settings{};
    settings.show_local_sources = true;
    find_ = fnFindCreate_(&settings);
    return find_ != nullptr;
  }

  void QueueFrame(std::vector<uint8_t> jpeg, const std::string& source, int width, int height) {
    {
      std::lock_guard<std::mutex> lock(frame_mu_);
      pending_jpeg_ = std::move(jpeg);
      pending_source_ = source;
      pending_width_ = width;
      pending_height_ = height;
      pending_ = true;
    }
    if (hwnd_) {
      PostMessage(hwnd_, NdiPluginHost::kFrameMessage, 0, 0);
    }
  }

  void EmitPending() {
    std::vector<uint8_t> jpeg;
    std::string source;
    int width = 0;
    int height = 0;
    {
      std::lock_guard<std::mutex> lock(frame_mu_);
      if (!pending_) {
        return;
      }
      pending_ = false;
      jpeg.swap(pending_jpeg_);
      source = pending_source_;
      width = pending_width_;
      height = pending_height_;
    }
    if (jpeg.empty() || !channel_) {
      return;
    }
    flutter::EncodableMap payload;
    payload[flutter::EncodableValue("jpeg")] = flutter::EncodableValue(jpeg);
    payload[flutter::EncodableValue("source")] = flutter::EncodableValue(source);
    payload[flutter::EncodableValue("width")] = flutter::EncodableValue(width);
    payload[flutter::EncodableValue("height")] = flutter::EncodableValue(height);
    channel_->InvokeMethod("frame", std::make_unique<flutter::EncodableValue>(payload));
  }

  std::unique_ptr<flutter::MethodChannel<flutter::EncodableValue>> channel_;
  HWND hwnd_ = nullptr;
  std::mutex frame_mu_;
  std::vector<uint8_t> pending_jpeg_;
  std::string pending_source_;
  int pending_width_ = 0;
  int pending_height_ = 0;
  bool pending_ = false;
  HMODULE dll_ = nullptr;
  void* find_ = nullptr;
  void* recv_ = nullptr;
  InitFn fnInit_ = nullptr;
  FindCreateFn fnFindCreate_ = nullptr;
  FindDestroyFn fnFindDestroy_ = nullptr;
  FindWaitFn fnFindWait_ = nullptr;
  FindSourcesFn fnFindSources_ = nullptr;
  RecvCreateFn fnRecvCreate_ = nullptr;
  RecvDestroyFn fnRecvDestroy_ = nullptr;
  RecvCaptureFn fnRecvCapture_ = nullptr;
  RecvFreeVideoFn fnRecvFreeVideo_ = nullptr;
  std::atomic<bool> capturing_{false};
  std::thread capture_;
  std::string connected_name_;
  uint64_t last_hash_ = 0;
};

void NdiPluginHost::Register(flutter::BinaryMessenger* messenger, HWND hwnd) {
  impl_ = std::make_unique<Impl>();
  impl_->Register(messenger, hwnd);
}

void NdiPluginHost::Shutdown() {
  if (impl_) {
    impl_->Shutdown();
  }
}

bool NdiPluginHost::HandleMessage(UINT message) {
  return impl_ ? impl_->HandleMessage(message) : false;
}
