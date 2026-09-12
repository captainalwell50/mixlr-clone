import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class NdiSource {
  NdiSource({required this.name, this.url = ''});

  final String name;
  final String url;
}

class NdiFrame {
  NdiFrame({required this.jpeg, required this.source, this.width = 0, this.height = 0});

  final Uint8List jpeg;
  final String source;
  final int width;
  final int height;
}

/// Native NDI finder/receiver (macOS + Windows). No-ops if the runtime is missing.
class NdiBridge extends ChangeNotifier {
  static const _channel = MethodChannel('soundmix/ndi');

  bool _listening = false;
  bool available = false;
  bool connected = false;
  String message = '';
  String? source;
  List<NdiSource> sources = const [];
  NdiFrame? lastFrame;
  void Function(NdiFrame frame)? onFrame;

  void _ensureListen() {
    if (_listening) return;
    _listening = true;
    _channel.setMethodCallHandler((call) async {
      if (call.method != 'frame' || call.arguments is! Map) return;
      final map = Map<Object?, Object?>.from(call.arguments as Map);
      final raw = map['jpeg'];
      Uint8List? jpeg;
      if (raw is Uint8List) {
        jpeg = raw;
      } else if (raw is ByteData) {
        jpeg = raw.buffer.asUint8List();
      } else if (raw is List<int>) {
        jpeg = Uint8List.fromList(raw);
      }
      if (jpeg == null || jpeg.isEmpty) return;
      final frame = NdiFrame(
        jpeg: jpeg,
        source: map['source'] as String? ?? source ?? 'NDI',
        width: (map['width'] as num?)?.toInt() ?? 0,
        height: (map['height'] as num?)?.toInt() ?? 0,
      );
      lastFrame = frame;
      onFrame?.call(frame);
      notifyListeners();
    });
  }

  Future<void> refreshStatus() async {
    _ensureListen();
    try {
      final raw = await _channel.invokeMethod<dynamic>('status');
      _applyStatus(raw);
    } catch (_) {
      available = false;
      message = 'NDI is not available in this Studio build.';
      notifyListeners();
    }
  }

  Future<void> listSources() async {
    _ensureListen();
    try {
      final raw = await _channel.invokeMethod<dynamic>('listSources');
      if (raw is Map) {
        available = raw['available'] == true;
        message = (raw['message'] as String?) ?? '';
        final rows = raw['sources'] as List<dynamic>? ?? [];
        sources = rows
            .whereType<Map>()
            .map((row) => NdiSource(
                  name: row['name'] as String? ?? '',
                  url: row['url'] as String? ?? '',
                ))
            .where((s) => s.name.isNotEmpty)
            .toList();
      }
    } catch (_) {
      available = false;
      sources = const [];
      message = 'NDI is not available in this Studio build.';
    }
    notifyListeners();
  }

  Future<void> connect(String name) async {
    _ensureListen();
    await _channel.invokeMethod<dynamic>('connect', {'name': name});
    source = name;
    connected = true;
    notifyListeners();
  }

  Future<void> disconnect() async {
    try {
      await _channel.invokeMethod<dynamic>('disconnect');
    } catch (_) {}
    connected = false;
    source = null;
    lastFrame = null;
    notifyListeners();
  }

  void _applyStatus(dynamic raw) {
    if (raw is! Map) return;
    available = raw['available'] == true;
    connected = raw['connected'] == true;
    source = raw['source'] as String?;
    message = (raw['message'] as String?) ?? '';
    notifyListeners();
  }

  @override
  void dispose() {
    onFrame = null;
    unawaited(disconnect());
    super.dispose();
  }
}
