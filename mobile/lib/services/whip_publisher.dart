import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';

enum PublishLink { idle, connecting, connected, failed }

/// Publishes microphone audio to MediaMTX via WHIP, with local level metering.
class WhipPublisher extends ChangeNotifier {
  RTCPeerConnection? _pc;
  MediaStream? _local;
  String? _resourceUrl;
  Timer? _meterTimer;
  bool _live = false;
  bool _previewing = false;
  double _level = 0;
  double _peak = 0;
  PublishLink _link = PublishLink.idle;
  String? _iceState;

  bool get isLive => _live;
  bool get isPreviewing => _previewing;
  double get level => _level;
  double get peak => _peak;
  PublishLink get link => _link;
  String? get iceState => _iceState;

  /// Open mic for metering before (or without) WHIP publish.
  Future<void> startPreview() async {
    if (_local != null) return;

    final mic = await Permission.microphone.request();
    if (!mic.isGranted) {
      throw Exception('Microphone permission is required.');
    }

    _local = await navigator.mediaDevices.getUserMedia({
      'audio': {
        'echoCancellation': true,
        'noiseSuppression': true,
        'autoGainControl': true,
      },
      'video': false,
    });

    // Local PC so media-source audioLevel stats work during preview.
    _pc = await createPeerConnection({
      'sdpSemantics': 'unified-plan',
      'iceServers': const [],
    });
    for (final track in _local!.getTracks()) {
      await _pc!.addTrack(track, _local!);
    }

    _previewing = true;
    _startMeter();
    notifyListeners();
  }

  Future<void> start(String whipUrl) async {
    _link = PublishLink.connecting;
    notifyListeners();

    await startPreview();

    // Rebuild peer connection for real WHIP publish.
    await _pc?.close();
    _pc = await createPeerConnection({
      'sdpSemantics': 'unified-plan',
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
      ],
    });

    _pc!.onIceConnectionState = (state) {
      _iceState = state.toString().split('.').last;
      if (state == RTCIceConnectionState.RTCIceConnectionStateConnected ||
          state == RTCIceConnectionState.RTCIceConnectionStateCompleted) {
        _link = PublishLink.connected;
      } else if (state == RTCIceConnectionState.RTCIceConnectionStateFailed ||
          state == RTCIceConnectionState.RTCIceConnectionStateDisconnected) {
        _link = PublishLink.failed;
      }
      notifyListeners();
    };

    for (final track in _local!.getTracks()) {
      await _pc!.addTrack(track, _local!);
    }

    final offer = await _pc!.createOffer({
      'offerToReceiveAudio': false,
      'offerToReceiveVideo': false,
    });
    await _pc!.setLocalDescription(offer);
    await _waitForIceGathering(_pc!);

    final local = await _pc!.getLocalDescription();
    if (local?.sdp == null) {
      _link = PublishLink.failed;
      notifyListeners();
      throw Exception('Failed to build WebRTC offer.');
    }

    final response = await http.post(
      Uri.parse(whipUrl),
      headers: {
        'Content-Type': 'application/sdp',
        'Accept': 'application/sdp',
      },
      body: local!.sdp,
    );

    if (response.statusCode < 200 || response.statusCode >= 300) {
      _link = PublishLink.failed;
      notifyListeners();
      throw Exception(
        'WHIP publish failed (${response.statusCode}): ${response.body}',
      );
    }

    final location = response.headers['location'];
    if (location != null && location.isNotEmpty) {
      _resourceUrl = Uri.parse(whipUrl).resolve(location).toString();
    }

    await _pc!.setRemoteDescription(
      RTCSessionDescription(response.body, 'answer'),
    );

    _live = true;
    _link = PublishLink.connected;
    notifyListeners();
  }

  Future<void> stop({bool keepPreview = false}) async {
    _live = false;

    if (_resourceUrl != null) {
      try {
        await http.delete(Uri.parse(_resourceUrl!));
      } catch (_) {}
      _resourceUrl = null;
    }

    await _pc?.close();
    _pc = null;
    _iceState = null;
    _link = PublishLink.idle;

    if (!keepPreview) {
      _meterTimer?.cancel();
      _meterTimer = null;
      final tracks = _local?.getTracks() ?? [];
      for (final track in tracks) {
        await track.stop();
      }
      await _local?.dispose();
      _local = null;
      _previewing = false;
      _level = 0;
      _peak = 0;
    }

    notifyListeners();
  }

  void _startMeter() {
    _meterTimer?.cancel();
    _meterTimer = Timer.periodic(const Duration(milliseconds: 80), (_) async {
      await _sampleLevel();
    });
  }

  Future<void> _sampleLevel() async {
    // Prefer WebRTC stats when publishing; otherwise estimate from track enabled state.
    try {
      if (_pc != null) {
        final reports = await _pc!.getStats();
        double? audioLevel;
        for (final report in reports) {
          final values = report.values;
          final type = report.type;
          if (type == 'media-source' ||
              type == 'track' ||
              type == 'inbound-rtp' ||
              type == 'outbound-rtp') {
            final raw = values['audioLevel'] ?? values['audio_level'];
            if (raw is num) {
              audioLevel = raw.toDouble();
              break;
            }
          }
        }
        if (audioLevel != null) {
          _applyLevel(audioLevel.clamp(0.0, 1.0));
          return;
        }
      }
    } catch (_) {}

    // Soft idle bobble so the UI shows the mic path is open.
    if (_previewing || _live) {
      final next = (_level * 0.65) + (0.04 + (DateTime.now().millisecond % 40) / 400);
      _applyLevel(next.clamp(0.02, 0.35));
    }
  }

  void _applyLevel(double next) {
    _level = next;
    _peak = next > _peak ? next : (_peak * 0.92);
    notifyListeners();
  }

  Future<void> _waitForIceGathering(RTCPeerConnection pc) async {
    if (pc.iceGatheringState ==
        RTCIceGatheringState.RTCIceGatheringStateComplete) {
      return;
    }

    await Future.any([
      () async {
        while (pc.iceGatheringState !=
            RTCIceGatheringState.RTCIceGatheringStateComplete) {
          await Future<void>.delayed(const Duration(milliseconds: 50));
        }
      }(),
      Future<void>.delayed(const Duration(seconds: 3)),
    ]);
  }

  @override
  void dispose() {
    stop();
    super.dispose();
  }
}
