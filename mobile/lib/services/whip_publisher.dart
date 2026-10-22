import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';

import '../platform_info.dart';
import 'publisher_reconnect.dart';
import 'whep_listener.dart';

enum PublishLink { idle, connecting, connected, reconnecting, replaced, failed, stopped }

typedef PublisherClaimRequest = Future<({bool owns, bool replaced})> Function(
  String claim,
  String intent,
);

const opusSendRates = {128000, 160000, 192000, 256000, 320000, 510000};

int clampOpusSendBitrate(int bitrate) =>
    opusSendRates.contains(bitrate) ? bitrate : 320000;

/// Phone studio has no Mono switch. Publish stereo, matching desktop Auto,
/// so a mono microphone does not downmix the programme.
String preferStudioPublishOpus(String sdp, {int bitrate = 320000}) {
  final rate = clampOpusSendBitrate(bitrate);
  final fmtp =
      'minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=$rate;maxplaybackrate=48000';
  return sdp.replaceAllMapped(
    RegExp(r'^a=fmtp:(\d+) (.*)$', multiLine: true),
    (match) {
      final pt = match.group(1)!;
      if (!RegExp('a=rtpmap:$pt opus/48000', caseSensitive: false).hasMatch(sdp)) {
        return match.group(0)!;
      }
      return 'a=fmtp:$pt $fmtp';
    },
  );
}

class MicDeviceOption {
  const MicDeviceOption({required this.deviceId, required this.label});

  final String deviceId;
  final String label;
}

/// Publishes microphone audio to MediaMTX via WHIP, with local level metering.
class WhipPublisher extends ChangeNotifier {
  RTCPeerConnection? _pc;
  MediaStream? _local;
  String? _resourceUrl;
  String? _whipUrl;
  int _opusSendBitrate = 320000;
  Timer? _meterTimer;
  Timer? _reconnectTimer;
  Timer? _disconnectGraceTimer;
  bool _live = false;
  bool _wantLive = false;
  bool _previewing = false;
  bool _muted = false;
  bool _republishing = false;
  double _level = 0;
  double _peak = 0;
  int _reconnectAttempt = 0;
  PublishLink _link = PublishLink.idle;
  String? _claimId;
  PublisherClaimRequest? _claimRequest;
  String? _iceState;
  String? _deviceId;
  String? _deviceLabel;
  List<MicDeviceOption> _devices = const [];

  bool get isLive => _live;
  bool get isPreviewing => _previewing;
  bool get isMuted => _muted;
  double get level => _level;
  double get peak => _peak;
  PublishLink get link => _link;
  String? get iceState => _iceState;
  String? get deviceId => _deviceId;
  String? get deviceLabel => _deviceLabel;
  List<MicDeviceOption> get devices => _devices;

  Future<void> refreshDevices() async {
    try {
      final all = await navigator.mediaDevices.enumerateDevices();
      _devices = all
          .where((d) => d.kind == 'audioinput')
          .map(
            (d) => MicDeviceOption(
              deviceId: d.deviceId,
              label: (d.label.isNotEmpty) ? d.label : 'Microphone',
            ),
          )
          .toList();
      notifyListeners();
    } catch (_) {}
  }

  Future<void> setMuted(bool muted) async {
    _muted = muted;
    final tracks = _local?.getAudioTracks() ?? [];
    for (final track in tracks) {
      track.enabled = !muted;
    }
    if (muted) {
      _level = 0;
      _peak = 0;
    }
    notifyListeners();
  }

  /// Switch input device while in preview/standby (not while publishing).
  Future<void> selectDevice(String deviceId) async {
    if (_deviceId == deviceId && _local != null) return;
    if (_live) {
      throw Exception('Pause or end the session before switching microphone.');
    }

    _deviceId = deviceId;
    final match = _devices.where((d) => d.deviceId == deviceId);
    _deviceLabel = match.isEmpty ? null : match.first.label;

    if (_local == null) {
      notifyListeners();
      return;
    }

    await stop(keepPreview: false);
    await startPreview(deviceId: deviceId);
  }

  /// Open mic for metering before (or without) WHIP publish.
  Future<void> startPreview({String? deviceId}) async {
    if (_local != null) return;

    // Keep media ADM attributes if listen already initialized WebRTC.
    await WhepListener.ensureWebRtcInitialized();

    // Runtime mic request happens in GoLiveScreen via PermissionDisclosure
    // (in-app disclosure before the OS prompt). Here we only verify status.
    if (PlatformInfo.usesPermissionHandlerForMic) {
      final mic = await Permission.microphone.status;
      if (!mic.isGranted) {
        final requested = await Permission.microphone.request();
        if (!requested.isGranted) {
          throw Exception('Microphone permission is required.');
        }
      }
    }

    await refreshDevices();
    final chosen = deviceId ?? _deviceId;

    // Desktop: OS prompts via getUserMedia. Prefer clean capture on macOS/Windows.
    final useCleanAudio = PlatformInfo.isDesktop;
    final audio = <String, dynamic>{
      'echoCancellation': !useCleanAudio,
      'noiseSuppression': !useCleanAudio,
      'autoGainControl': !useCleanAudio,
      if (chosen != null && chosen.isNotEmpty) 'deviceId': chosen,
    };
    _local = await navigator.mediaDevices.getUserMedia({
      'audio': audio,
      'video': false,
    });

    final tracks = _local!.getAudioTracks();
    if (tracks.isNotEmpty) {
      String? resolvedId = chosen;
      try {
        final settings = tracks.first.getSettings();
        final fromTrack = settings['deviceId'];
        if (fromTrack is String && fromTrack.isNotEmpty) {
          resolvedId = fromTrack;
        }
      } catch (_) {}
      _deviceId = resolvedId;
      final match = _devices.where((d) => d.deviceId == _deviceId);
      final trackLabel = tracks.first.label;
      _deviceLabel = match.isEmpty
          ? ((trackLabel != null && trackLabel.isNotEmpty)
              ? trackLabel
              : 'Microphone')
          : match.first.label;
      if (_muted) {
        for (final t in tracks) {
          t.enabled = false;
        }
      }
    }

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

  Future<void> start(
    String whipUrl, {
    PublisherClaimRequest? claim,
    int? opusSendBitrate,
  }) async {
    if (claim != null) _claimRequest = claim;
    if (opusSendBitrate != null) {
      _opusSendBitrate = clampOpusSendBitrate(opusSendBitrate);
    }
    _whipUrl = whipUrl;
    _wantLive = true;
    _clearReconnectTimers(resetAttempts: false);
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
        _reconnectAttempt = 0;
        _disconnectGraceTimer?.cancel();
        _disconnectGraceTimer = null;
        notifyListeners();
      } else if (state == RTCIceConnectionState.RTCIceConnectionStateFailed) {
        if (_link == PublishLink.replaced || _link == PublishLink.stopped) return;
        _link = PublishLink.failed;
        notifyListeners();
        _scheduleRepublish();
      } else if (state ==
          RTCIceConnectionState.RTCIceConnectionStateDisconnected) {
        _link = PublishLink.reconnecting;
        notifyListeners();
        _armDisconnectGrace();
      }
    };

    for (final track in _local!.getTracks()) {
      await _pc!.addTrack(track, _local!);
    }

    final offer = await _pc!.createOffer({
      'offerToReceiveAudio': false,
      'offerToReceiveVideo': false,
    });
    final tunedOffer = preferStudioPublishOpus(
      offer.sdp ?? '',
      bitrate: _opusSendBitrate,
    );
    await _pc!.setLocalDescription(RTCSessionDescription(tunedOffer, 'offer'));
    await _waitForIceGathering(_pc!);

    final local = await _pc!.getLocalDescription();
    if (local?.sdp == null) {
      _link = PublishLink.failed;
      notifyListeners();
      throw Exception('Failed to build WebRTC offer.');
    }

    await _establishClaim();
    late final http.Response response;
    try {
      response = await http.post(
      Uri.parse(whipUrl),
      headers: {
        'Content-Type': 'application/sdp',
        'Accept': 'application/sdp',
      },
      body: preferStudioPublishOpus(local!.sdp!, bitrate: _opusSendBitrate),
    );

      if (response.statusCode < 200 || response.statusCode >= 300) {
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
    } catch (e) {
      await _releaseClaim();
      _link = PublishLink.failed;
      notifyListeners();
      rethrow;
    }

    _live = true;
    _link = PublishLink.connected;
    _reconnectAttempt = 0;
    notifyListeners();
  }

  void _armDisconnectGrace() {
    if (!_wantLive || _link == PublishLink.replaced || _link == PublishLink.stopped) {
      return;
    }
    _disconnectGraceTimer?.cancel();
    _disconnectGraceTimer = Timer(const Duration(seconds: 12), () {
      _disconnectGraceTimer = null;
      if (!_wantLive) return;
      if (_link == PublishLink.connected ||
          _link == PublishLink.replaced ||
          _link == PublishLink.stopped) {
        return;
      }
      _scheduleRepublish();
    });
  }

  void _scheduleRepublish() {
    if (!_wantLive ||
        _whipUrl == null ||
        _republishing ||
        _link == PublishLink.replaced ||
        _link == PublishLink.stopped) {
      return;
    }
    if (_reconnectTimer != null) return;
    _link = PublishLink.reconnecting;
    notifyListeners();
    final seconds = (1 << _reconnectAttempt.clamp(0, 4)).clamp(2, 30);
    _reconnectAttempt = (_reconnectAttempt + 1).clamp(0, 5);
    _reconnectTimer = Timer(Duration(seconds: seconds), () {
      _reconnectTimer = null;
      unawaited(_republish());
    });
  }

  Future<void> _republish() async {
    final url = _whipUrl;
    if (!_wantLive ||
        url == null ||
        _republishing ||
        _link == PublishLink.replaced ||
        _link == PublishLink.stopped) {
      return;
    }
    _republishing = true;
    _link = PublishLink.reconnecting;
    notifyListeners();
    try {
      if (await _ownershipLost()) {
        await _markReplaced();
        return;
      }
      // Tear down WHIP session but keep mic preview tracks.
      if (_resourceUrl != null) {
        try {
          await http.delete(Uri.parse(_resourceUrl!));
        } catch (_) {}
        _resourceUrl = null;
      }
      await _pc?.close();
      _pc = null;
      await start(url);
    } catch (_) {
      _link = PublishLink.failed;
      notifyListeners();
      _scheduleRepublish();
    } finally {
      _republishing = false;
    }
  }

  void _clearReconnectTimers({bool resetAttempts = true}) {
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _disconnectGraceTimer?.cancel();
    _disconnectGraceTimer = null;
    if (resetAttempts) {
      _reconnectAttempt = 0;
    }
  }

  Future<void> _markReplaced() async {
    _wantLive = false;
    _live = false;
    _link = PublishLink.replaced;
    _clearReconnectTimers();
    final pc = _pc;
    _pc = null;
    _resourceUrl = null;
    await pc?.close();
    notifyListeners();
  }

  Future<void> _establishClaim() async {
    final request = _claimRequest;
    if (request == null) return;
    _claimId = newPublisherClaimId();
    try {
      await request(_claimId!, 'claim');
    } catch (_) {}
  }

  Future<void> _releaseClaim() async {
    final request = _claimRequest;
    final claim = _claimId;
    _claimId = null;
    if (request == null || claim == null) return;
    try {
      await request(claim, 'release');
    } catch (_) {}
  }

  Future<bool> _ownershipLost() async {
    final request = _claimRequest;
    final claim = _claimId;
    if (request == null || claim == null) return false;
    try {
      final result = await request(claim, 'check');
      return decidePublisherReconnect(
            owns: result.owns,
            replaced: result.replaced,
          ) ==
          PublisherReconnectDecision.replaced;
    } catch (_) {
      return decidePublisherReconnect(checkFailed: true) ==
          PublisherReconnectDecision.replaced;
    }
  }

  Future<void> stop({bool keepPreview = false}) async {
    _live = false;
    _wantLive = false;
    _clearReconnectTimers();
    await _releaseClaim();

    if (_resourceUrl != null) {
      try {
        await http.delete(Uri.parse(_resourceUrl!));
      } catch (_) {}
      _resourceUrl = null;
    }

    await _pc?.close();
    _pc = null;
    _iceState = null;
    _link = PublishLink.stopped;
    _whipUrl = null;

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
    if (_muted) {
      _applyLevel(0);
      return;
    }

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
