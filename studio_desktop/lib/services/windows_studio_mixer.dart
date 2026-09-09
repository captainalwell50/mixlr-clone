import 'dart:async';
import 'dart:io';

import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';

typedef MixerHostMessage = void Function(Map<String, dynamic> data);

/// Windows Studio mixer: WebRTC mic + WHIP publish, Just Audio playlist preview.
/// Playlist is mixed locally (cue/speakers). Listeners hear the selected microphone.
class WindowsStudioMixer {
  WindowsStudioMixer({required this.onMessage});

  final MixerHostMessage onMessage;

  final Map<String, _WinTrack> _tracks = {};
  MediaStream? _micStream;
  RTCPeerConnection? _pc;
  String? _whipResource;
  String? _selectedDeviceId;
  String? _selectedOutputId = 'default';
  String _micPermission = 'notDetermined';
  bool _playlistMute = false;
  bool _playlistCue = false;
  double _playlistGain = 1;
  double _masterGain = 1;
  String _publish = 'idle';

  Future<void> start() async {
    onMessage({'type': 'ready'});
    onMessage({'type': 'status', 'message': 'Windows mixer ready — enable microphone'});
    await listDevices();
    await listOutputs();
  }

  String get micPermission => _micPermission;

  Future<String> refreshMicPermission() async {
    return _micPermission;
  }

  Future<bool> requestMicAccess() async {
    try {
      final stream = await navigator.mediaDevices.getUserMedia({
        'audio': true,
        'video': false,
      });
      await _replaceMicStream(stream);
      _micPermission = 'authorized';
      onMessage({
        'type': 'status',
        'message': 'Standby — mixer armed. Queue tracks, then go live.',
      });
      await listDevices();
      return true;
    } catch (_) {
      _micPermission = 'denied';
      return false;
    }
  }

  Future<void> armMic({String? deviceId}) async {
    final stream = await navigator.mediaDevices.getUserMedia({
      'audio': _audioConstraints(deviceId),
      'video': false,
    });
    await _replaceMicStream(stream);
    _micPermission = 'authorized';
    _selectedDeviceId = deviceId ?? _selectedDeviceId ?? 'default';
    onMessage({
      'type': 'devices',
      'inputs': await _inputPayload(),
      'selected': _selectedDeviceId,
    });
  }

  Future<void> listDevices() async {
    onMessage({
      'type': 'devices',
      'inputs': await _inputPayload(),
      'selected': _selectedDeviceId,
    });
  }

  Future<void> listOutputs() async {
    final devices = await Helper.audiooutputs;
    final payload = <Map<String, String>>[
      {'deviceId': 'default', 'label': 'System default'},
    ];
    for (final d in devices) {
      payload.add({
        'deviceId': d.deviceId,
        'label': d.label.isEmpty ? d.deviceId : d.label,
      });
    }
    onMessage({
      'type': 'outputs',
      'outputs': payload,
      'selected': _selectedOutputId,
    });
  }

  Future<void> setInputDevice(String deviceId) async {
    _selectedDeviceId = deviceId;
    if (deviceId == 'none') {
      await _replaceMicStream(null);
      return;
    }
    await armMic(deviceId: deviceId);
    if (_publish == 'connected') {
      try {
        await Helper.selectAudioInput(deviceId);
      } catch (_) {}
    }
  }

  Future<void> setOutputDevice(String deviceId) async {
    _selectedOutputId = deviceId;
    onMessage({
      'type': 'outputs',
      'outputs': [
        {'deviceId': 'default', 'label': 'System default'},
      ],
      'selected': _selectedOutputId,
    });
  }

  Future<void> reloadDevices() async {
    await listDevices();
    await listOutputs();
  }

  void setGains({double? mic, double? playlist, double? master}) {
    if (playlist != null) _playlistGain = playlist;
    if (master != null) _masterGain = master;
    _applyPlaylistVolume();
  }

  void setMutes({bool? mic, bool? playlist}) {
    if (playlist != null) _playlistMute = playlist;
    _applyPlaylistVolume();
  }

  void setCues({bool? mic, bool? playlist}) {
    if (playlist != null) _playlistCue = playlist;
    _applyPlaylistVolume();
  }

  Future<String?> queueTrack({
    required String id,
    required String title,
    required String url,
    int? assetId,
  }) async {
    _tracks[id]?.player.dispose();
    final track = _WinTrack(id: id, title: title, assetId: assetId);
    _tracks[id] = track;
    _emitTracks();
    try {
      final path = await _ensureLocalFile(id, url);
      track.localPath = path;
      await track.player.setFilePath(path);
      track.ready = true;
      track.duration = track.player.duration?.inMilliseconds.toDouble() ?? 0;
      _emitTracks();
      return path;
    } catch (e) {
      _tracks.remove(id);
      _emitTracks();
      throw Exception('Could not queue that track. ${e.toString()}');
    }
  }

  Future<void> play(String id) async {
    final track = _tracks[id];
    if (track == null || !track.ready) {
      throw Exception('That track is not in the playlist. Press Queue, then Play.');
    }
    _applyPlaylistVolume();
    await track.player.seek(Duration.zero);
    await track.player.play();
    track.playing = true;
    track.player.playerStateStream.listen((state) {
      if (state.processingState == ProcessingState.completed) {
        track.playing = false;
        _emitTracks();
      }
    });
    _emitTracks();
    if (_publish == 'connected') {
      onMessage({
        'type': 'status',
        'message':
            'Playing “${track.title}” in Studio. Listeners hear the microphone on Windows.',
      });
    }
  }

  Future<void> pause(String id) async {
    final track = _tracks[id];
    if (track == null) return;
    await track.player.pause();
    track.playing = false;
    _emitTracks();
  }

  Future<void> restart(String id) => play(id);

  Future<void> remove(String id) async {
    final track = _tracks.remove(id);
    await track?.player.dispose();
    _emitTracks();
  }

  Future<void> clearTracks() async {
    for (final track in _tracks.values) {
      await track.player.dispose();
    }
    _tracks.clear();
    _emitTracks();
  }

  Future<void> goLive(String whipUrl) async {
    await stopPublish();
    if (_micStream == null) {
      await armMic(deviceId: _selectedDeviceId);
    }
    final mic = _micStream;
    if (mic == null) {
      throw Exception('Arm a microphone in SOURCE before going live.');
    }
    onMessage({'type': 'publish', 'state': 'connecting'});
    final pc = await createPeerConnection({
      'sdpSemantics': 'unified-plan',
      'iceServers': [
        {
          'urls': ['stun:stun.l.google.com:19302'],
        },
      ],
    });
    _pc = pc;
    for (final track in mic.getAudioTracks()) {
      await pc.addTrack(track, mic);
    }
    final offer = await pc.createOffer({
      'mandatory': {
        'OfferToReceiveAudio': false,
        'OfferToReceiveVideo': false,
      },
    });
    final sdp = preferHighQualityOpus(offer.sdp ?? '');
    await pc.setLocalDescription(RTCSessionDescription(sdp, 'offer'));
    await _waitForIce(pc);
    final local = await pc.getLocalDescription();
    if (local == null || (local.sdp ?? '').isEmpty) {
      throw Exception('No local session description yet');
    }
    final res = await http.post(
      Uri.parse(whipUrl),
      headers: {
        'Content-Type': 'application/sdp',
        'Accept': 'application/sdp',
      },
      body: local.sdp,
    );
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('WHIP failed (${res.statusCode})');
    }
    final loc = res.headers['location'];
    if (loc != null && loc.isNotEmpty) {
      _whipResource = Uri.parse(whipUrl).resolve(loc).toString();
    }
    await pc.setRemoteDescription(RTCSessionDescription(res.body, 'answer'));
    _publish = 'connected';
    onMessage({'type': 'publish', 'state': 'connected'});
  }

  Future<void> stopPublish() async {
    final resource = _whipResource;
    _whipResource = null;
    if (resource != null) {
      try {
        await http.delete(Uri.parse(resource));
      } catch (_) {}
    }
    await _pc?.close();
    _pc = null;
    _publish = 'idle';
    onMessage({'type': 'publish', 'state': 'idle'});
  }

  Future<void> dispose() async {
    await stopPublish();
    await clearTracks();
    await _replaceMicStream(null);
  }

  dynamic _audioConstraints(String? deviceId) {
    if (deviceId == null || deviceId.isEmpty || deviceId == 'default') {
      return true;
    }
    return {'deviceId': deviceId};
  }

  Future<void> _replaceMicStream(MediaStream? next) async {
    for (final track in _micStream?.getTracks() ?? []) {
      await track.stop();
    }
    _micStream = next;
  }

  Future<List<Map<String, String>>> _inputPayload() async {
    final devices = await Helper.enumerateDevices('audioinput');
    final payload = <Map<String, String>>[
      {'deviceId': 'default', 'label': 'System default microphone'},
      {'deviceId': 'none', 'label': 'None'},
    ];
    for (final d in devices) {
      payload.add({
        'deviceId': d.deviceId,
        'label': d.label.isEmpty ? 'Microphone' : d.label,
      });
    }
    return payload;
  }

  Future<String> _ensureLocalFile(String id, String url) async {
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      return url;
    }
    final dir = await getApplicationSupportDirectory();
    final cache = Directory('${dir.path}/SoundMixStudio/playlist');
    await cache.create(recursive: true);
    final ext = url.contains('.') ? url.split('.').last.split('?').first : 'mp3';
    final dest = File('${cache.path}/sm-track-${id.replaceAll('/', '_')}.$ext');
    if (dest.existsSync() && dest.lengthSync() > 0) {
      return dest.path;
    }
    final res = await http.get(Uri.parse(url));
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('Could not download that track (HTTP ${res.statusCode}).');
    }
    await dest.writeAsBytes(res.bodyBytes, flush: true);
    return dest.path;
  }

  void _applyPlaylistVolume() {
    final local = _playlistMute ? 0.0 : (_playlistCue ? 1.0 : 0.35) * _playlistGain * _masterGain;
    for (final track in _tracks.values) {
      track.player.setVolume(local.clamp(0, 1));
    }
  }

  void _emitTracks() {
    onMessage({
      'type': 'tracks',
      'tracks': _tracks.values
          .map(
            (t) => {
              'id': t.id,
              'title': t.title,
              'assetId': t.assetId,
              'ready': t.ready,
              'playing': t.playing,
              'currentTime': 0,
              'duration': (t.duration / 1000),
            },
          )
          .toList(),
    });
  }

  Future<void> _waitForIce(RTCPeerConnection pc) async {
    if (pc.iceGatheringState == RTCIceGatheringState.RTCIceGatheringStateComplete) {
      return;
    }
    final done = Completer<void>();
    pc.onIceGatheringState = (state) {
      if (state == RTCIceGatheringState.RTCIceGatheringStateComplete && !done.isCompleted) {
        done.complete();
      }
    };
    await done.future.timeout(const Duration(seconds: 2), onTimeout: () {});
  }
}

class _WinTrack {
  _WinTrack({required this.id, required this.title, this.assetId});

  final String id;
  final String title;
  final int? assetId;
  final player = AudioPlayer();
  String? localPath;
  bool ready = false;
  bool playing = false;
  double duration = 0;
}

/// Same Opus fmtp as web / macOS Studio.
String preferHighQualityOpus(String sdp) {
  const bitrate = 510000;
  const fmtp =
      'minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=$bitrate;maxplaybackrate=48000';
  final lines = sdp.replaceAll('\r\n', '\n').replaceAll('\r', '\n').split('\n');
  final opusPts = <String>{};
  for (final line in lines) {
    final match = RegExp(r'^a=rtpmap:(\d+) opus/', caseSensitive: false).firstMatch(line);
    if (match != null) {
      opusPts.add(match.group(1)!);
    }
  }
  if (opusPts.isEmpty) return sdp;
  final out = <String>[];
  final seen = <String>{};
  for (final line in lines) {
    String? matched;
    for (final pt in opusPts) {
      if (line.startsWith('a=fmtp:$pt')) {
        matched = pt;
        break;
      }
    }
    if (matched != null) {
      if (seen.add(matched)) {
        out.add('a=fmtp:$matched $fmtp');
      }
      continue;
    }
    out.add(line);
  }
  for (final pt in opusPts) {
    if (seen.add(pt)) {
      out.add('a=fmtp:$pt $fmtp');
    }
  }
  return out.join('\n');
}
