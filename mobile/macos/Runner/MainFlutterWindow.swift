import Cocoa
import FlutterMacOS

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    self.contentViewController = flutterViewController

    // Desktop Studio default window — roomy for meters + controls.
    let size = NSSize(width: 1180, height: 760)
    self.setContentSize(size)
    self.minSize = NSSize(width: 880, height: 560)
    self.title = "Sound Mix Live Studio"
    self.center()

    RegisterGeneratedPlugins(registry: flutterViewController)

    super.awakeFromNib()
  }
}
