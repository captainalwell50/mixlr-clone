import 'dart:async';

import 'package:audio_session/audio_session.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:http/http.dart' as http;

import 'android_listen_audio.dart';

enum WhepConnectionState {
  idle,
  connecting,
  connected,
  reconnecting,
  failed,
  closed,
}

/// Pulls Opus audio from MediaMTX via WHEP (same path as the web listen portal).
class WhepListener {
  RTCPeerConnection? _pc;
  RTCVideoRenderer? _renderer;
  MediaStream? _remote;
  MediaStreamTrack? _audioTrack;
  String? _resourceUrl;
  bool _userEnabled = true;
  bool _disposed = false;
  bool _hasAudioTrack = false;
  bool _iceConnected = false;
  String? _iceState;
  WhepConnectionState _connectionState = WhepConnectionState.idle;
  final _connectionController =
      StreamController<WhepConnectionState>.broadcast();
  Timer? _iceWatchdog;
  final List<Timer> _speakerReassertTimers = [];
  Completer<void>? _ready;

  static bool _webrtcReady = false;

  bool get isPlaying =>
      _userEnabled && _connectionState == WhepConnectionState.connected;
  bool get isConnected =>
      _connectionState == WhepConnectionState.connected;
  String? get iceState => _iceState;
  WhepConnectionState get connectionState => _connectionState;
  Stream<WhepConnectionState> get connectionStates =>
      _connectionController.stream;
  RTCVideoRenderer? get renderer => _renderer;

  /// Must run before any other flutter_webrtc call. The plugin's first
  /// [WebRTC.initialize] creates the Android [JavaAudioDeviceModule].
  ///
  /// ADM AudioTrack attributes are **baked at initialize** — voiceCommunication
  /// permanently prefers the earpiece on OEM phones. Listen therefore uses
  /// media / music attributes so playout routes like a music player.
  static Future<void> ensureWebRtcInitialized() async {
    if (_webrtcReady || kIsWeb) return;
    if (WebRTC.platformIsAndroid) {
      final listenAudio = _androidListenAudioConfig();
      await WebRTC.initialize(options: {
        'androidAudioConfiguration': listenAudio.toMap(),
      });
      try {
        await Helper.setAndroidAudioConfiguration(listenAudio);
      } catch (_) {}
      await _configureAudioSessionForListen();
    } else if (WebRTC.platformIsIOS) {
      await WebRTC.initialize();
      await _configureAudioSessionForListen();
      try {
        await Helper.setAppleAudioIOMode(
          AppleAudioIOMode.remoteOnly,
          preferSpeakerOutput: true,
        );
        // Explicit default-to-speaker for playAndRecord fallbacks.
        await Helper.setAppleAudioConfiguration(
          AppleAudioConfiguration(
            appleAudioCategory: AppleAudioCategory.playAndRecord,
            appleAudioCategoryOptions: {
              AppleAudioCategoryOption.defaultToSpeaker,
              AppleAudioCategoryOption.allowBluetooth,
              AppleAudioCategoryOption.mixWithOthers,
            },
            appleAudioMode: AppleAudioMode.videoChat,
          ),
        );
      } catch (_) {}
    } else {
      await WebRTC.initialize();
    }
    _webrtcReady = true;
  }

  /// Media playback routing — loudspeaker by default (not earpiece / call).
  ///
  /// Matches flutter_webrtc's [AndroidAudioConfiguration.media] with an
  /// explicit music content type. forceHandleAudioRouting keeps AudioSwitch
  /// able to prefer Speakerphone even in MODE_NORMAL.
  static AndroidAudioConfiguration _androidListenAudioConfig() {
    return AndroidAudioConfiguration(
      manageAudioFocus: true,
      androidAudioMode: AndroidAudioMode.normal,
      androidAudioFocusMode: AndroidAudioFocusMode.gain,
      androidAudioStreamType: AndroidAudioStreamType.music,
      androidAudioAttributesUsageType: AndroidAudioAttributesUsageType.media,
      androidAudioAttributesContentType:
          AndroidAudioAttributesContentType.music,
      forceHandleAudioRouting: true,
    );
  }

  static Future<void> _configureAudioSessionForListen() async {
    try {
      final session = await AudioSession.instance;
      await session.configure(const AudioSessionConfiguration(
        avAudioSessionCategory: AVAudioSessionCategory.playback,
        avAudioSessionCategoryOptions: AVAudioSessionCategoryOptions.duckOthers,
        avAudioSessionMode: AVAudioSessionMode.defaultMode,
        androidAudioAttributes: AndroidAudioAttributes(
          contentType: AndroidAudioContentType.music,
          usage: AndroidAudioUsage.media,
        ),
        androidAudioFocusGainType: AndroidAudioFocusGainType.gain,
      ));
      await session.setActive(true);
    } catch (e) {
      debugPrint('audio_session configure skipped: $e');
    }
  }

  Future<void> start(String whepUrl) async {
    await stop();
    if (_disposed) return;
    _userEnabled = true;
    _hasAudioTrack = false;
    _iceConnected = false;
    _audioTrack = null;
    _ready = Completer<void>();
    _setConnection(WhepConnectionState.connecting);

    await ensureWebRtcInitialized();
    await _configureAudioSessionForListen();
    await AndroidListenAudio.preparePlayback();
    await _configurePlaybackAudio();

    try {
      _renderer = RTCVideoRenderer();
      await _renderer!.initialize();
    } catch (_) {
      // Audio can still work without a video sink attachment.
      _renderer = null;
    }

    _pc = await createPeerConnection({
      'sdpSemantics': 'unified-plan',
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
      ],
    });

    _pc!.onTrack = (RTCTrackEvent event) {
      void attach(MediaStream stream) {
        _remote = stream;
        _renderer?.srcObject = stream;
        if (event.track.kind == 'audio') {
          _audioTrack = event.track;
          _hasAudioTrack = true;
          event.track.enabled = _userEnabled;
          unawaited(_primeAudioTrack(event.track));
          // Track attach is when WebRTC starts playout — force loudspeaker now.
          unawaited(_configurePlaybackAudio());
          _tryCompleteReady();
        }
      }

      if (event.streams.isNotEmpty) {
        attach(event.streams.first);
        return;
      }

      // Some answers omit stream ids — synthesize a remote stream.
      unawaited(() async {
        final stream = _remote ?? await createLocalMediaStream('whep-remote');
        await stream.addTrack(event.track);
        attach(stream);
      }());
    };

    _pc!.onIceConnectionState = (state) {
      _iceState = state.toString().split('.').last;
      switch (state) {
        case RTCIceConnectionState.RTCIceConnectionStateConnected:
        case RTCIceConnectionState.RTCIceConnectionStateCompleted:
          _iceConnected = true;
          _iceWatchdog?.cancel();
          _iceWatchdog = null;
          unawaited(_configurePlaybackAudio());
          _tryCompleteReady();
          // Restore LISTENING after a brief ICE blip (start() already finished).
          if (_hasAudioTrack &&
              _userEnabled &&
              (_connectionState == WhepConnectionState.reconnecting ||
                  _connectionState == WhepConnectionState.connecting)) {
            _setConnection(WhepConnectionState.connected);
          }
          break;
        case RTCIceConnectionState.RTCIceConnectionStateDisconnected:
          _iceConnected = false;
          if (_connectionState == WhepConnectionState.connected) {
            _setConnection(WhepConnectionState.reconnecting);
            _armIceWatchdog();
          }
          break;
        case RTCIceConnectionState.RTCIceConnectionStateFailed:
          _iceConnected = false;
          _setConnection(WhepConnectionState.failed);
          final ready = _ready;
          if (ready != null && !ready.isCompleted) {
            ready.completeError(Exception('WebRTC connection failed.'));
          }
          break;
        case RTCIceConnectionState.RTCIceConnectionStateClosed:
          _iceConnected = false;
          if (_connectionState != WhepConnectionState.idle) {
            _setConnection(WhepConnectionState.closed);
          }
          break;
        default:
          break;
      }
    };

    _pc!.onConnectionState = (state) {
      if (state == RTCPeerConnectionState.RTCPeerConnectionStateFailed) {
        _setConnection(WhepConnectionState.failed);
        final ready = _ready;
        if (ready != null && !ready.isCompleted) {
          ready.completeError(Exception('Peer connection failed.'));
        }
      } else if (state ==
          RTCPeerConnectionState.RTCPeerConnectionStateConnected) {
        _iceConnected = true;
        unawaited(_configurePlaybackAudio());
        _tryCompleteReady();
      }
    };

    await _pc!.addTransceiver(
      kind: RTCRtpMediaType.RTCRtpMediaTypeAudio,
      init: RTCRtpTransceiverInit(direction: TransceiverDirection.RecvOnly),
    );

    final offer = await _pc!.createOffer({
      'offerToReceiveAudio': true,
      'offerToReceiveVideo': false,
    });
    final tuned = RTCSessionDescription(
      _tuneOpusSdp(offer.sdp ?? ''),
      offer.type,
    );
    await _pc!.setLocalDescription(tuned);
    await _waitForIceGathering(_pc!);

    final local = await _pc!.getLocalDescription();
    if (local?.sdp == null) {
      _setConnection(WhepConnectionState.failed);
      throw Exception('Failed to build WebRTC listen offer.');
    }

    final response = await http
        .post(
          Uri.parse(whepUrl),
          headers: {
            'Content-Type': 'application/sdp',
            'Accept': 'application/sdp',
          },
          body: local!.sdp,
        )
        .timeout(const Duration(seconds: 12));

    if (response.statusCode < 200 || response.statusCode >= 300) {
      _setConnection(WhepConnectionState.failed);
      throw Exception(
        'WHEP listen failed (${response.statusCode}): ${response.body}',
      );
    }

    final location = response.headers['location'];
    if (location != null && location.isNotEmpty) {
      _resourceUrl = Uri.parse(whepUrl).resolve(location).toString();
    }

    await _pc!.setRemoteDescription(
      RTCSessionDescription(response.body, 'answer'),
    );

    await _configurePlaybackAudio();

    try {
      await _ready!.future.timeout(
        const Duration(seconds: 15),
        onTimeout: () =>
            throw TimeoutException('Timed out waiting for live audio.'),
      );
    } catch (e) {
      _setConnection(WhepConnectionState.failed);
      rethrow;
    }

    // Prefer real RTP before LISTENING; ICE+track alone can still be silent.
    final gotPackets = await _waitForInboundAudio(
      const Duration(seconds: 6),
    );
    if (!gotPackets && !_iceConnected) {
      _setConnection(WhepConnectionState.failed);
      throw Exception('Live audio did not start. Tap Retry.');
    }

    await _applyEnabled();
    await _configurePlaybackAudio();

    if (!_hasAudioTrack || !_userEnabled) {
      _setConnection(WhepConnectionState.failed);
      throw Exception('Live audio track unavailable.');
    }

    _setConnection(WhepConnectionState.connected);
  }

  Future<void> setEnabled(bool enabled) async {
    _userEnabled = enabled;
    await _applyEnabled();
    if (enabled) {
      await _configurePlaybackAudio();
    }
  }

  Future<void> _applyEnabled() async {
    final tracks = _remote?.getAudioTracks() ?? [];
    for (final track in tracks) {
      track.enabled = _userEnabled;
      if (_userEnabled) {
        await _primeAudioTrack(track);
      }
    }
  }

  Future<void> _primeAudioTrack(MediaStreamTrack track) async {
    track.enabled = _userEnabled;
    try {
      await Helper.setVolume(1.0, track);
    } catch (_) {}
    await _configurePlaybackAudio();
  }

  /// Public re-apply for lifecycle resume (loudspeaker can reset on pause).
  Future<void> ensureLoudspeaker() => _configurePlaybackAudio();

  Future<void> stop() async {
    _iceWatchdog?.cancel();
    _iceWatchdog = null;
    _cancelSpeakerReassert();
    _ready = null;
    _hasAudioTrack = false;
    _iceConnected = false;
    _audioTrack = null;

    if (_resourceUrl != null) {
      try {
        await http.delete(Uri.parse(_resourceUrl!)).timeout(
              const Duration(seconds: 3),
            );
      } catch (_) {}
      _resourceUrl = null;
    }

    final tracks = _remote?.getTracks() ?? [];
    for (final track in tracks) {
      try {
        await track.stop();
      } catch (_) {}
    }
    await _remote?.dispose();
    _remote = null;

    if (_renderer != null) {
      _renderer!.srcObject = null;
      await _renderer!.dispose();
      _renderer = null;
    }

    await _pc?.close();
    _pc = null;
    _iceState = null;
    await AndroidListenAudio.releasePlayback();
    _setConnection(WhepConnectionState.idle);
  }

  Future<void> _configurePlaybackAudio() async {
    if (WebRTC.platformIsAndroid) {
      // Media session first so just_audio / OS agree this is playback, not a call.
      await _configureAudioSessionForListen();
      // 1) Native AudioManager: MODE_NORMAL + media focus + speaker.
      await AndroidListenAudio.forceSpeaker();
      try {
        await Helper.setAndroidAudioConfiguration(_androidListenAudioConfig());
      } catch (_) {}
      // 2) flutter_webrtc AudioSwitch → prefer Speakerphone device.
      try {
        await Helper.setSpeakerphoneOn(true);
      } catch (_) {}
      // Re-assert after AudioSwitch activates (can race with onTrack / ICE).
      await AndroidListenAudio.forceSpeaker();
      _armSpeakerReassert();
    }
    if (WebRTC.platformIsIOS) {
      try {
        await _configureAudioSessionForListen();
        await Helper.ensureAudioSession();
        await Helper.setAppleAudioIOMode(
          AppleAudioIOMode.remoteOnly,
          preferSpeakerOutput: true,
        );
        await Helper.setAppleAudioConfiguration(
          AppleAudioConfiguration(
            appleAudioCategory: AppleAudioCategory.playAndRecord,
            appleAudioCategoryOptions: {
              AppleAudioCategoryOption.defaultToSpeaker,
              AppleAudioCategoryOption.allowBluetooth,
              AppleAudioCategoryOption.mixWithOthers,
            },
            appleAudioMode: AppleAudioMode.videoChat,
          ),
        );
        await Helper.setSpeakerphoneOn(true);
      } catch (_) {}
    }
  }

  /// WebRTC often resets the Android route shortly after ICE/connect.
  void _armSpeakerReassert() {
    if (!WebRTC.platformIsAndroid) return;
    _cancelSpeakerReassert();
    for (final ms in const <int>[300, 900, 2000, 4000]) {
      _speakerReassertTimers.add(Timer(Duration(milliseconds: ms), () {
        if (_disposed || _connectionState == WhepConnectionState.idle) return;
        unawaited(_configurePlaybackAudioOnce());
      }));
    }
  }

  void _cancelSpeakerReassert() {
    for (final t in _speakerReassertTimers) {
      t.cancel();
    }
    _speakerReassertTimers.clear();
  }

  /// Single pass without re-arming the reassert timer (avoids recursion).
  Future<void> _configurePlaybackAudioOnce() async {
    if (!WebRTC.platformIsAndroid) return;
    await AndroidListenAudio.forceSpeaker();
    try {
      await Helper.setAndroidAudioConfiguration(_androidListenAudioConfig());
    } catch (_) {}
    try {
      await Helper.setSpeakerphoneOn(true);
    } catch (_) {}
    await AndroidListenAudio.forceSpeaker();
  }

  void _tryCompleteReady() {
    final ready = _ready;
    if (ready == null || ready.isCompleted) return;
    // Require remote audio + ICE (or peer) connected before UI can listen.
    if (_hasAudioTrack && _iceConnected && _userEnabled) {
      ready.complete();
    }
  }

  Future<bool> _waitForInboundAudio(Duration timeout) async {
    final pc = _pc;
    if (pc == null) return false;
    final deadline = DateTime.now().add(timeout);
    while (DateTime.now().isBefore(deadline)) {
      if (_disposed || _connectionState == WhepConnectionState.failed) {
        return false;
      }
      try {
        final reports = await pc.getStats(_audioTrack);
        for (final report in reports) {
          final type = report.type;
          final values = report.values;
          final kind = values['kind']?.toString() ??
              values['mediaType']?.toString() ??
              '';
          final isAudio = kind == 'audio' ||
              type == 'inbound-rtp' ||
              type.contains('inbound');
          if (!isAudio) continue;
          final bytes = values['bytesReceived'];
          final packets = values['packetsReceived'];
          if (bytes is num && bytes > 0) return true;
          if (packets is num && packets > 0) return true;
          final level = values['audioLevel'];
          if (level is num && level > 0) return true;
        }
      } catch (_) {}
      // Track present + ICE up is enough if stats lag (some OEM builds).
      if (_hasAudioTrack &&
          _iceConnected &&
          DateTime.now().isAfter(
            deadline.subtract(const Duration(milliseconds: 1500)),
          )) {
        return true;
      }
      await Future<void>.delayed(const Duration(milliseconds: 250));
    }
    return _hasAudioTrack && _iceConnected;
  }

  void _armIceWatchdog() {
    _iceWatchdog?.cancel();
    _iceWatchdog = Timer(const Duration(seconds: 8), () {
      if (_connectionState == WhepConnectionState.reconnecting) {
        _setConnection(WhepConnectionState.failed);
      }
    });
  }

  void _setConnection(WhepConnectionState next) {
    if (_connectionState == next) return;
    _connectionState = next;
    if (!_connectionController.isClosed) {
      _connectionController.add(next);
    }
  }

  /// Prefer stereo Opus + FEC; disable DTX (matches web listen.js).
  String _tuneOpusSdp(String sdp) {
    return sdp.replaceAllMapped(
      RegExp(r'^a=fmtp:(\d+) (.*)$', multiLine: true),
      (match) {
        final pt = match.group(1)!;
        final params = match.group(2)!;
        if (!RegExp('a=rtpmap:$pt opus/48000', caseSensitive: false)
            .hasMatch(sdp)) {
          return match.group(0)!;
        }
        if (RegExp(r'stereo=1', caseSensitive: false).hasMatch(params) &&
            RegExp(r'maxaveragebitrate=', caseSensitive: false)
                .hasMatch(params) &&
            RegExp(r'usedtx=0', caseSensitive: false).hasMatch(params)) {
          return match.group(0)!;
        }
        return 'a=fmtp:$pt minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=510000;maxplaybackrate=48000';
      },
    );
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

  Future<void> dispose() async {
    _disposed = true;
    await stop();
    await _connectionController.close();
  }
}
