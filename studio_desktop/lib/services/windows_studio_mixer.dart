import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';

import 'audio_file_extension.dart';
import 'publisher_reconnect.dart';

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
  String mixLayout = 'auto';
  String? _claimUrl;
  String? _authToken;
  String? _claimId;
  String? _whipUrl;
  bool _wantLive = false;
  bool _republishing = false;
  int _reconnectAttempt = 0;
  Timer? _reconnectTimer;
  Timer? _disconnectGraceTimer;

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
        'audio': studioCleanAudioConstraints(),
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
    String? filename,
    String? mimeType,
  }) async {
    _tracks[id]?.player.dispose();
    final track = _WinTrack(id: id, title: title, assetId: assetId);
    _tracks[id] = track;
    _emitTracks();
    try {
      final path = await _ensureLocalFile(
        id,
        url,
        filename: filename,
        mimeType: mimeType,
      );
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

  int _opusSendBitrate = 320000;

  Future<void> goLive(
    String whipUrl, {
    String? claimUrl,
    String? authToken,
    int? opusSendBitrate,
  }) async {
    if (claimUrl != null && claimUrl.isNotEmpty) _claimUrl = claimUrl;
    if (authToken != null && authToken.isNotEmpty) _authToken = authToken;
    const allowed = {128000, 160000, 192000, 256000, 320000, 510000};
    if (opusSendBitrate != null && allowed.contains(opusSendBitrate)) {
      _opusSendBitrate = opusSendBitrate;
    }
    _whipUrl = whipUrl;
    _wantLive = true;
    await stopPublish(releaseOwnership: false);
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
    pc.onIceConnectionState = (state) {
      if (pc != _pc || _publish == 'replaced' || !_wantLive) return;
      if (state == RTCIceConnectionState.RTCIceConnectionStateConnected ||
          state == RTCIceConnectionState.RTCIceConnectionStateCompleted) {
        _reconnectAttempt = 0;
        _disconnectGraceTimer?.cancel();
        _disconnectGraceTimer = null;
        _publish = 'connected';
        onMessage({'type': 'publish', 'state': 'connected'});
      } else if (state == RTCIceConnectionState.RTCIceConnectionStateFailed) {
        _scheduleRepublish();
      } else if (state == RTCIceConnectionState.RTCIceConnectionStateDisconnected) {
        _armDisconnectGrace();
      }
    };
    for (final track in mic.getAudioTracks()) {
      await pc.addTrack(track, mic);
    }
    final offer = await pc.createOffer({
      'mandatory': {
        'OfferToReceiveAudio': false,
        'OfferToReceiveVideo': false,
      },
    });
    final sdp = preferHighQualityOpus(offer.sdp ?? '', stereo: _publishStereo(mic), bitrate: _opusSendBitrate);
    await pc.setLocalDescription(RTCSessionDescription(sdp, 'offer'));
    await _waitForIce(pc);
    final gathered = await pc.getLocalDescription();
    if (gathered == null || (gathered.sdp ?? '').isEmpty) {
      throw Exception('No local session description yet');
    }
    final publishSdp = preferHighQualityOpus(gathered.sdp ?? '', stereo: _publishStereo(mic), bitrate: _opusSendBitrate);
    await _establishClaim();
    try {
      final res = await http.post(
        Uri.parse(whipUrl),
        headers: {
          'Content-Type': 'application/sdp',
          'Accept': 'application/sdp',
        },
        body: publishSdp,
      );
      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('WHIP failed (${res.statusCode})');
      }
      final loc = res.headers['location'];
      if (loc != null && loc.isNotEmpty) {
        _whipResource = resolveWhipResource(whipUrl, loc);
      }
      await pc.setRemoteDescription(RTCSessionDescription(res.body, 'answer'));
    } catch (e) {
      await _releaseClaim();
      rethrow;
    }
    _publish = 'connected';
    onMessage({'type': 'publish', 'state': 'connected'});
  }

  Future<void> stopPublish({bool releaseOwnership = true}) async {
    _clearReconnectTimers();
    if (releaseOwnership) {
      _wantLive = false;
      await _releaseClaim();
    }
    final resource = _whipResource;
    _whipResource = null;
    if (resource != null) {
      try {
        await http.delete(Uri.parse(resource));
      } catch (_) {}
    }
    await _pc?.close();
    _pc = null;
    if (_publish != 'replaced') {
      _publish = 'idle';
      onMessage({'type': 'publish', 'state': 'idle'});
    }
  }

  void _armDisconnectGrace() {
    if (!_wantLive || _publish == 'replaced') return;
    _disconnectGraceTimer?.cancel();
    _disconnectGraceTimer = Timer(const Duration(seconds: 12), () {
      _disconnectGraceTimer = null;
      if (!_wantLive || _publish == 'connected' || _publish == 'replaced') return;
      _scheduleRepublish();
    });
  }

  void _scheduleRepublish() {
    if (!_wantLive || _whipUrl == null || _republishing || _publish == 'replaced') return;
    if (_reconnectTimer != null) return;
    _publish = 'recovering';
    onMessage({
      'type': 'publish',
      'state': 'recovering',
      'message': 'Audio link dropped — reconnecting…',
    });
    final seconds = (1 << _reconnectAttempt.clamp(0, 4)).clamp(2, 30);
    _reconnectAttempt = (_reconnectAttempt + 1).clamp(0, 5);
    _reconnectTimer = Timer(Duration(seconds: seconds), () {
      _reconnectTimer = null;
      unawaited(_republish());
    });
  }

  Future<void> _republish() async {
    final url = _whipUrl;
    if (!_wantLive || url == null || _republishing || _publish == 'replaced') return;
    _republishing = true;
    try {
      if (await _ownershipLost()) {
        await _markReplaced();
        return;
      }
      await goLive(url);
    } catch (_) {
      _publish = 'failed';
      onMessage({'type': 'publish', 'state': 'failed'});
      _scheduleRepublish();
    } finally {
      _republishing = false;
    }
  }

  Future<void> _markReplaced() async {
    _wantLive = false;
    _publish = 'replaced';
    _clearReconnectTimers();
    final pc = _pc;
    _pc = null;
    _whipResource = null;
    await pc?.close();
    onMessage({
      'type': 'publish',
      'state': 'replaced',
      'message': publisherReplacedNotice,
    });
  }

  Future<void> _establishClaim() async {
    final url = _claimUrl;
    final token = _authToken;
    if (url == null || token == null || token.isEmpty) return;
    _claimId = newPublisherClaimId();
    try {
      await _postClaim(intent: 'claim', claim: _claimId!);
    } catch (_) {}
  }

  Future<void> _releaseClaim() async {
    final claim = _claimId;
    _claimId = null;
    if (claim == null) return;
    try {
      await _postClaim(intent: 'release', claim: claim);
    } catch (_) {}
  }

  Future<bool> _ownershipLost() async {
    final claim = _claimId;
    if (claim == null || _claimUrl == null) return false;
    try {
      final body = await _postClaim(intent: 'check', claim: claim);
      return decidePublisherReconnect(
            owns: body['owns'] == true,
            replaced: body['replaced'] == true,
          ) ==
          PublisherReconnectDecision.replaced;
    } catch (_) {
      return decidePublisherReconnect(checkFailed: true) ==
          PublisherReconnectDecision.replaced;
    }
  }

  Future<Map<String, dynamic>> _postClaim({
    required String intent,
    required String claim,
  }) async {
    final url = _claimUrl;
    final token = _authToken;
    if (url == null || token == null || token.isEmpty) {
      throw Exception('Publisher claim is not configured.');
    }
    final res = await http.post(
      Uri.parse(url),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({'claim': claim, 'intent': intent}),
    );
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('Publisher claim failed (${res.statusCode}).');
    }
    final decoded = jsonDecode(res.body);
    return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
  }

  void _clearReconnectTimers() {
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _disconnectGraceTimer?.cancel();
    _disconnectGraceTimer = null;
  }

  Future<void> dispose() async {
    await stopPublish();
    await clearTracks();
    await _replaceMicStream(null);
  }

  void setLayout(String mode) {
    mixLayout = (mode == 'mono' || mode == 'stereo') ? mode : 'auto';
  }

  bool _publishStereo(MediaStream mic) {
    return mixLayoutPublishesStereo(mixLayout);
  }

  dynamic _audioConstraints(String? deviceId) {
    final audio = Map<String, dynamic>.from(studioCleanAudioConstraints());
    if (deviceId != null && deviceId.isNotEmpty && deviceId != 'default') {
      audio['deviceId'] = deviceId;
    }
    if (mixLayout == 'mono') {
      audio['channelCount'] = {'ideal': 1};
    } else if (mixLayout == 'stereo') {
      audio['channelCount'] = {'ideal': 2};
    }
    return audio;
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

  Future<String> _ensureLocalFile(
    String id,
    String url, {
    String? filename,
    String? mimeType,
  }) async {
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      final local = File(url);
      if (await local.exists()) return url;
      throw Exception('That audio file is no longer on this computer.');
    }
    final dir = await getApplicationSupportDirectory();
    final cache = Directory('${dir.path}/SoundMixStudio/playlist');
    await cache.create(recursive: true);
    final ext = audioFileExtension(url: url, filename: filename, mimeType: mimeType);
    final dest = File('${cache.path}/sm-track-${safeTrackCacheId(id)}.$ext');
    if (dest.existsSync() && dest.lengthSync() > 0) {
      return dest.path;
    }
    final res = await http.get(Uri.parse(url));
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('Could not download that track (HTTP ${res.statusCode}).');
    }
    if (res.bodyBytes.isEmpty) {
      throw Exception('Downloaded track was empty.');
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

/// Same as web Studio CLEAN_AUDIO — NS/AGC/EC crush music on Listen.
Map<String, dynamic> studioCleanAudioConstraints() {
  return {
    'echoCancellation': false,
    'noiseSuppression': false,
    'autoGainControl': false,
    'sampleRate': {'ideal': 48000},
    'googEchoCancellation': false,
    'googNoiseSuppression': false,
    'googAutoGainControl': false,
    'googHighpassFilter': false,
  };
}

/// Programme-bus stereo for WHIP. Auto is stereo-capable; only Mono collapses.
/// Mic channelCount remains available via [mediaStreamLooksStereo] for diagnostics.
bool mixLayoutPublishesStereo(String mixLayout) {
  return mixLayout != 'mono';
}

bool mediaStreamLooksStereo(MediaStream stream) {
  for (final track in stream.getAudioTracks()) {
    try {
      final n = track.getSettings()['channelCount'];
      if (n is num && n >= 2) {
        return true;
      }
    } catch (_) {}
  }
  return false;
}

String resolveWhipResource(String whipUrl, String loc) {
  final base = Uri.parse(whipUrl);
  var abs = base.resolve(loc);
  final host = abs.host;
  final private = host == 'localhost' ||
      host == '127.0.0.1' ||
      host.startsWith('172.') ||
      host.startsWith('192.168.') ||
      host.startsWith('10.');
  final mtxPort = abs.hasPort && (abs.port == 8889 || abs.port == 8888);
  if (private || mtxPort) {
    var path = abs.path;
    if (path.startsWith('/live/')) {
      path = '/rtc$path';
    }
    return base.replace(path: path, query: abs.hasQuery ? abs.query : null).toString();
  }
  if (abs.host == base.host &&
      abs.path.startsWith('/live/') &&
      base.path.startsWith('/rtc/')) {
    return abs.replace(path: '/rtc${abs.path}').toString();
  }
  return abs.toString();
}

String stripNonOpusAudioCodecs(String sdp) {
  final opusPts = <String>{};
  for (final match in RegExp(
    r'^a=rtpmap:(\d+) opus/48000',
    caseSensitive: false,
    multiLine: true,
  ).allMatches(sdp)) {
    opusPts.add(match.group(1)!);
  }
  if (opusPts.isEmpty) return sdp;
  final lines = sdp.replaceAll('\r\n', '\n').replaceAll('\r', '\n').split('\n');
  final out = <String>[];
  var inAudio = false;
  for (final line in lines) {
    if (line.startsWith('m=audio ')) {
      inAudio = true;
      final parts = line.split(' ');
      final kept = <String>[
        ...parts.take(3),
        ...parts.skip(3).where(opusPts.contains),
      ];
      out.add(kept.join(' '));
      continue;
    }
    if (line.startsWith('m=')) {
      inAudio = false;
    }
    if (inAudio) {
      final pt = RegExp(r'^a=(?:rtpmap|fmtp|rtcp-fb):(\d+)\b').firstMatch(line);
      if (pt != null && !opusPts.contains(pt.group(1))) {
        continue;
      }
    }
    out.add(line);
  }
  return out.join('\n');
}

String preferHighQualityOpus(String sdp, {bool stereo = false, int bitrate = 320000}) {
  sdp = stripNonOpusAudioCodecs(sdp);
  const allowed = {128000, 160000, 192000, 256000, 320000, 510000};
  if (!allowed.contains(bitrate)) bitrate = 320000;
  final flag = stereo ? 1 : 0;
  final fmtp =
      'minptime=20;useinbandfec=1;usedtx=0;stereo=$flag;sprop-stereo=$flag;maxaveragebitrate=$bitrate;maxplaybackrate=48000';
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
