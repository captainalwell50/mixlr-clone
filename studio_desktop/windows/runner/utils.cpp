#include "utils.h"

#include <flutter_windows.h>
#include <io.h>
#include <shlobj.h>
#include <stdio.h>
#include <windows.h>

#include <fstream>
#include <iostream>

void CreateAndAttachConsole() {
  if (::AllocConsole()) {
    FILE *unused;
    if (freopen_s(&unused, "CONOUT$", "w", stdout)) {
      _dup2(_fileno(stdout), 1);
    }
    if (freopen_s(&unused, "CONOUT$", "w", stderr)) {
      _dup2(_fileno(stdout), 2);
    }
    std::ios::sync_with_stdio();
    FlutterDesktopResyncOutputStreams();
  }
}

std::vector<std::string> GetCommandLineArguments() {
  // Convert the UTF-16 command line arguments to UTF-8 for the Engine to use.
  int argc;
  wchar_t** argv = ::CommandLineToArgvW(::GetCommandLineW(), &argc);
  if (argv == nullptr) {
    return std::vector<std::string>();
  }

  std::vector<std::string> command_line_arguments;

  // Skip the first argument as it's the binary name.
  for (int i = 1; i < argc; i++) {
    command_line_arguments.push_back(Utf8FromUtf16(argv[i]));
  }

  ::LocalFree(argv);

  return command_line_arguments;
}

std::string Utf8FromUtf16(const wchar_t* utf16_string) {
  if (utf16_string == nullptr) {
    return std::string();
  }
  unsigned int target_length = ::WideCharToMultiByte(
      CP_UTF8, WC_ERR_INVALID_CHARS, utf16_string,
      -1, nullptr, 0, nullptr, nullptr)
    -1; // remove the trailing null character
  int input_length = (int)wcslen(utf16_string);
  std::string utf8_string;
  if (target_length == 0 || target_length > utf8_string.max_size()) {
    return utf8_string;
  }
  utf8_string.resize(target_length);
  int converted_length = ::WideCharToMultiByte(
      CP_UTF8, WC_ERR_INVALID_CHARS, utf16_string,
      input_length, utf8_string.data(), target_length, nullptr, nullptr);
  if (converted_length == 0) {
    return std::string();
  }
  return utf8_string;
}

std::wstring GetExecutableDirectory() {
  wchar_t module_path[MAX_PATH];
  DWORD length = ::GetModuleFileNameW(nullptr, module_path, MAX_PATH);
  if (length == 0 || length >= MAX_PATH) {
    return std::wstring();
  }
  wchar_t* slash = wcsrchr(module_path, L'\\');
  if (slash == nullptr) {
    return std::wstring();
  }
  *slash = L'\0';
  return std::wstring(module_path);
}

bool SetWorkingDirectoryToExe() {
  const std::wstring dir = GetExecutableDirectory();
  if (dir.empty()) {
    return false;
  }
  return ::SetCurrentDirectoryW(dir.c_str()) != 0;
}

bool DataFolderLooksValid() {
  const std::wstring dir = GetExecutableDirectory();
  if (dir.empty()) {
    return false;
  }
  const std::wstring icu = dir + L"\\data\\icudtl.dat";
  const std::wstring assets = dir + L"\\data\\flutter_assets";
  const DWORD icu_attr = ::GetFileAttributesW(icu.c_str());
  const DWORD assets_attr = ::GetFileAttributesW(assets.c_str());
  return icu_attr != INVALID_FILE_ATTRIBUTES &&
         assets_attr != INVALID_FILE_ATTRIBUTES;
}

static std::wstring StartupLogPath() {
  wchar_t appdata[MAX_PATH];
  if (FAILED(::SHGetFolderPathW(nullptr, CSIDL_LOCAL_APPDATA, nullptr, 0,
                                appdata))) {
    return std::wstring();
  }
  std::wstring folder = std::wstring(appdata) + L"\\SoundMixStudio";
  ::CreateDirectoryW(folder.c_str(), nullptr);
  return folder + L"\\startup.log";
}

void WriteStartupLog(const wchar_t* message) {
  const std::wstring path = StartupLogPath();
  if (path.empty() || message == nullptr) {
    return;
  }
  FILE* file = nullptr;
  if (_wfopen_s(&file, path.c_str(), L"a") != 0 || file == nullptr) {
    return;
  }
  SYSTEMTIME now;
  ::GetLocalTime(&now);
  fwprintf(file, L"%04u-%02u-%02u %02u:%02u:%02u  %s\n", now.wYear, now.wMonth,
           now.wDay, now.wHour, now.wMinute, now.wSecond, message);
  fclose(file);
}

void ShowStartupError(const wchar_t* message) {
  WriteStartupLog(message);
  const std::wstring path = StartupLogPath();
  std::wstring body = message;
  if (!path.empty()) {
    body += L"\n\nLog: ";
    body += path;
  }
  ::MessageBoxW(nullptr, body.c_str(), L"Sound Mix Live Studio",
                MB_OK | MB_ICONERROR);
}
