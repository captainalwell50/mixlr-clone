import Cocoa
import FlutterMacOS

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    self.contentViewController = flutterViewController
    self.setContentSize(NSSize(width: 1240, height: 800))
    self.minSize = NSSize(width: 980, height: 640)
    self.title = "Sound Mix Live Studio"
    self.center()
    RegisterGeneratedPlugins(registry: flutterViewController)
    MixerEnginePlugin.register(
      with: flutterViewController.registrar(forPlugin: "MixerEnginePlugin"),
      hostView: flutterViewController.view
    )
    super.awakeFromNib()
  }
}
