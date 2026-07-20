import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';

/// Publishes microphone audio to MediaMTX via WHIP.
class WhipPublisher {
  RTCPeerConnection? _pc;
  MediaStream? _local;
  String? _resourceUrl;
  bool _live = false;

  bool get isLive => _live;

  Future<void> start(String whipUrl) async {
    await stop();

    final mic = await Permission.microphone.request();
    if (!mic.isGranted) {
      throw Exception('Microphone permission is required to go live.');
    }

    _pc = await createPeerConnection({
      'sdpSemantics': 'unified-plan',
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
      ],
    });

    _local = await navigator.mediaDevices.getUserMedia({
      'audio': {
        'echoCancellation': true,
        'noiseSuppression': true,
        'autoGainControl': true,
      },
      'video': false,
    });

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
  }

  Future<void> stop() async {
    _live = false;

    if (_resourceUrl != null) {
      try {
        await http.delete(Uri.parse(_resourceUrl!));
      } catch (_) {}
      _resourceUrl = null;
    }

    final tracks = _local?.getTracks() ?? [];
    for (final track in tracks) {
      await track.stop();
    }
    await _local?.dispose();
    _local = null;

    await _pc?.close();
    _pc = null;
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
}
