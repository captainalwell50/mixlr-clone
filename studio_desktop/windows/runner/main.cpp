#include <flutter/dart_project.h>
#include <flutter/flutter_view_controller.h>
#include <windows.h>

#include "flutter_window.h"
#include "utils.h"

namespace {

LONG WINAPI StudioCrashFilter(EXCEPTION_POINTERS* info) {
  wchar_t buffer[256];
  const DWORD code =
      info && info->ExceptionRecord ? info->ExceptionRecord->ExceptionCode : 0;
  swprintf_s(buffer, L"Studio crashed on startup (code 0x%08X).", code);
  ShowStartupError(buffer);
  return EXCEPTION_EXECUTE_HANDLER;
}

}  // namespace

int APIENTRY wWinMain(_In_ HINSTANCE instance, _In_opt_ HINSTANCE prev,
                      _In_ wchar_t *command_line, _In_ int show_command) {
  ::SetUnhandledExceptionFilter(StudioCrashFilter);

  // Attach to console when present (e.g., 'flutter run') or create a
  // new console when running with a debugger.
  if (!::AttachConsole(ATTACH_PARENT_PROCESS) && ::IsDebuggerPresent()) {
    CreateAndAttachConsole();
  }

  if (!SetWorkingDirectoryToExe()) {
    ShowStartupError(
        L"Could not set the working folder to the Studio directory.");
    return EXIT_FAILURE;
  }
  WriteStartupLog(L"Starting Sound Mix Live Studio.");
  if (!DataFolderLooksValid()) {
    ShowStartupError(
        L"Studio files are incomplete. Unzip the whole folder (keep the data "
        L"directory next to soundmix_studio.exe), then double-click "
        L"\"Run Studio.cmd\".");
    return EXIT_FAILURE;
  }

  // Initialize COM, so that it is available for use in the library and/or
  // plugins.
  ::CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);

  flutter::DartProject project(L"data");

  std::vector<std::string> command_line_arguments =
      GetCommandLineArguments();

  project.set_dart_entrypoint_arguments(std::move(command_line_arguments));

  FlutterWindow window(project);
  Win32Window::Point origin(10, 10);
  Win32Window::Size size(1280, 720);
  if (!window.Create(L"Sound Mix Live Studio", origin, size)) {
    ShowStartupError(
        L"Studio could not open a window. If you downloaded this zip from the "
        L"web, right-click the zip → Properties → Unblock, extract again, then "
        L"run \"Run Studio.cmd\".");
    ::CoUninitialize();
    return EXIT_FAILURE;
  }
  window.SetQuitOnClose(true);
  WriteStartupLog(L"Window created.");

  ::MSG msg;
  while (::GetMessage(&msg, nullptr, 0, 0)) {
    ::TranslateMessage(&msg);
    ::DispatchMessage(&msg);
  }

  ::CoUninitialize();
  return EXIT_SUCCESS;
}
