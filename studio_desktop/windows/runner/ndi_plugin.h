#ifndef RUNNER_NDI_PLUGIN_H_
#define RUNNER_NDI_PLUGIN_H_

#include <memory>

#include <windows.h>

namespace flutter {
class BinaryMessenger;
}

class NdiPluginHost {
 public:
  NdiPluginHost();
  ~NdiPluginHost();
  NdiPluginHost(const NdiPluginHost&) = delete;
  NdiPluginHost& operator=(const NdiPluginHost&) = delete;

  static constexpr UINT kFrameMessage = WM_APP + 77;

  void Register(flutter::BinaryMessenger* messenger, HWND hwnd);
  void Shutdown();
  bool HandleMessage(UINT message);

 private:
  class Impl;
  std::unique_ptr<Impl> impl_;
};

#endif
