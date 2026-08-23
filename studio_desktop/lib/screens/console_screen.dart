import 'dart:async';
import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/live_board_sync.dart';
import '../services/mixer_bridge.dart';
import '../theme.dart';
import '../widgets/console_chassis.dart';
import '../widgets/scripture_panel.dart';
import '../widgets/song_panel.dart';

class ConsoleScreen extends StatefulWidget {
  const ConsoleScreen({super.key});

  @override
  State<ConsoleScreen> createState() => _ConsoleScreenState();
}

enum _StudioTab { live, advance }

class _ConsoleScreenState extends State<ConsoleScreen> {
  final _mixer = MixerBridge();
  CreatorHome? _home;
  StreamSummary? _stream;
  EventSummary? _event;
  List<LibraryAsset> _library = const [];
  List<GalleryItem> _gallery = const [];
  _StudioTab _tab = _StudioTab.live;
  bool _loading = true;
  bool _busy = false;
  bool _onAir = false;
  bool _paused = false;
  bool _micMute = false;
  bool _playlistMute = false;
  bool _micCue = false;
  bool _playlistCue = false;
  double _micGain = 1.0;
  double _playlistGain = 1.0;
  double _masterGain = 1.0;
  bool _layoutMono = true;
  String? _error;
  String _status = 'Loading mixer…';
  DateTime? _liveStartedAt;
  Timer? _tick;
  Duration _elapsed = Duration.zero;

  int? get _openEventId {
    final event = _event;
    if (event == null || !event.isOpen) return null;
    return event.id;
  }

  bool get _galleryReady => _openEventId != null || _onAir || _paused;

  @override
  void initState() {
    super.initState();
    _mixer.addListener(_onMixer);
    _bootstrap();
  }

  void _onMixer() {
    if (!mounted) return;
    setState(() {
      if (_mixer.status != null) _status = _mixer.status!;
      // Ignore one-shot eval plumbing noise; real failures still surface via catch.
      final err = _mixer.error;
      if (err != null && !err.contains('unsupported type')) {
        _error = err;
      }
    });
  }

  Future<void> _bootstrap() async {
    final auth = context.read<AuthState>();
    final api = auth.api;
    try {
      final home = await api.creatorHome();
      final preferred = auth.preferredStreamUuid;
      StreamSummary? stream;
      if (preferred != null) {
        for (final s in home.streams) {
          if (s.uuid == preferred) {
            stream = s;
            break;
          }
        }
      }
      stream ??= home.stream ??
          (home.streams.isNotEmpty ? home.streams.first : null);

      final serverLive = stream?.isLive == true;
      setState(() {
        _home = home;
        _stream = stream;
        _event = home.openEvent;
        _loading = false;
        _paused = serverLive;
        _onAir = false;
        _status = stream == null
            ? 'No stream configured for this account.'
            : serverLive
                ? 'Server shows an open live — press Go live / Resume to reconnect publish.'
                : 'Starting mixer engine…';
      });
      if (stream == null) return;

      setState(() => _status = 'Starting native mixer…');
      await _mixer.startEngine();
      await _mixer.listDevices();
      await _mixer.listOutputs();

      // Mixlr-style: never re-prompt if TCC already decided. Auto-arm when authorized.
      final status = await _mixer.refreshMicPermissionStatus();
      if (status == 'authorized') {
        try {
          final saved = await _mixer.savedMicDeviceId();
          if (saved != null && saved.isNotEmpty && saved != 'none') {
            await _mixer.setInputDevice(saved);
          } else {
            await _mixer.armMic();
          }
          await _mixer.setGains(
            mic: _micGain,
            playlist: _playlistGain,
            master: _masterGain,
          );
          _mixer.clearError();
          if (!mounted) return;
          setState(() {
            _error = null;
            _status = serverLive
                ? 'Mixer armed — resume to reconnect publish for the open event.'
                : 'Standby — mixer armed. Queue tracks, then go live.';
          });
        } catch (e) {
          if (!mounted) return;
          setState(() {
            _error = e.toString().replaceFirst('Exception: ', '');
            _status = 'Native mixer ready — pick a microphone in SOURCE.';
          });
        }
      } else if (status == 'denied') {
        if (!mounted) return;
        setState(() {
          _error =
              'Microphone access is denied. Enable it in System Settings → Privacy & Security → Microphone.';
          _status = 'Native mixer ready — microphone permission required.';
        });
      } else {
        if (!mounted) return;
        setState(() {
          _status = 'Native mixer ready — choose a microphone to continue.';
        });
      }

      await _refreshLibrary();
      await _refreshGallery();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.message;
        _status = 'Could not load studio. Check your connection, then sign in again.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceFirst('Exception: ', '');
        _status = 'Console ready — mixer engine unavailable.';
      });
    }
  }

  Future<void> _selectStream(String? uuid) async {
    if (uuid == null || uuid == _stream?.uuid) return;
    final home = _home;
    if (home == null) return;
    StreamSummary? next;
    for (final s in home.streams) {
      if (s.uuid == uuid) {
        next = s;
        break;
      }
    }
    if (next == null) return;
    if (_onAir) {
      setState(() => _error = 'End or pause the current broadcast before switching channels.');
      return;
    }
    final selected = next;
    await context.read<AuthState>().rememberStreamUuid(selected.uuid);
    if (!mounted) return;
    setState(() {
      _stream = selected;
      _event = null;
      _gallery = const [];
      _library = const [];
      _paused = selected.isLive;
      _error = null;
      _status = 'Switched to ${selected.title}.';
    });
    await _refreshLibrary();
    await _refreshGallery();
  }

  Future<void> _enableMic() async {
    setState(() {
      _busy = true;
      _error = null;
      _status = 'Arming microphone…';
    });
    try {
      // Prompts the OS only on first grant (notDetermined).
      final granted = await _mixer.ensureMicAccess(promptIfNeeded: true);
      if (!granted) {
        throw Exception(
          'Microphone access is denied. Enable it in System Settings → Privacy & Security → Microphone.',
        );
      }
      final saved = await _mixer.savedMicDeviceId();
      if (saved != null && saved.isNotEmpty && saved != 'none') {
        await _mixer.setInputDevice(saved);
      } else {
        await _mixer.armMic();
      }
      await _mixer.setGains(mic: _micGain, playlist: _playlistGain, master: _masterGain);
      await _mixer.listDevices();
      await _mixer.listOutputs();
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = 'Standby — mixer armed. Queue tracks, then go live.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _refreshLibrary() async {
    final stream = _stream;
    if (stream == null) return;
    try {
      final api = context.read<AuthState>().api;
      final assets = await api.library(stream.uuid);
      if (mounted) setState(() => _library = assets);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _refreshGallery() async {
    final stream = _stream;
    if (stream == null) return;
    try {
      final api = context.read<AuthState>().api;
      final items = await api.gallery(stream.uuid, eventId: _openEventId);
      if (mounted) setState(() => _gallery = items);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  void dispose() {
    _tick?.cancel();
    _mixer.removeListener(_onMixer);
    _mixer.dispose();
    super.dispose();
  }

  String get _clock {
    final h = _elapsed.inHours;
    final m = (_elapsed.inMinutes % 60).toString().padLeft(2, '0');
    final s = (_elapsed.inSeconds % 60).toString().padLeft(2, '0');
    return h > 0 ? '$h:$m:$s' : '$m:$s';
  }

  Future<void> _goLive() async {
    final stream = _stream;
    if (stream == null) return;
    if (!_mixer.isArmed) {
      setState(() {
        _error =
            'Arm a microphone in SOURCE before going live (Scarlett or Built-in).';
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _status = _paused
          ? 'Resuming event & reconnecting publish…'
          : 'Starting event & connecting publish…';
    });
    final api = context.read<AuthState>().api;
    try {
      // Match web Studio: create/resume the service event first, then WHIP.
      final session = await api.goLive(
        stream.uuid,
        eventId: _openEventId,
      );
      final whipUrl = session.whipUrl.isNotEmpty
          ? session.whipUrl
          : (await api.publish(stream.uuid)).whipUrl;
      if (whipUrl.isEmpty) {
        throw Exception('No WHIP URL from server.');
      }
      if (session.event != null) {
        _event = session.event;
      }
      if (session.stream != null) {
        _stream = session.stream;
      }
      await _mixer.goLive(whipUrl);
      _liveStartedAt = DateTime.now();
      _elapsed = Duration.zero;
      _tick?.cancel();
      _tick = Timer.periodic(const Duration(seconds: 1), (_) {
        if (_liveStartedAt == null || !mounted) return;
        setState(() => _elapsed = DateTime.now().difference(_liveStartedAt!));
      });
      if (!mounted) return;
      setState(() {
        _onAir = true;
        _paused = false;
        _error = null;
        _status = _micCue || _playlistCue
            ? 'On air — cue is on; use headphones to avoid feedback.'
            : 'On air — mic + playlist mix publishing.';
      });
      await _refreshGallery();
    } on ApiException catch (e) {
      await _mixer.stopPublish();
      if (!mounted) return;
      setState(() {
        _onAir = false;
        // Keep the open event so Resume / gallery still work (matches web Studio).
        if (_event != null) _paused = true;
        _error = e.message;
        _status = _event != null
            ? 'Publish failed — event is open. Fix the issue, then Resume.'
            : _status;
      });
    } catch (e) {
      await _mixer.stopPublish();
      if (!mounted) return;
      setState(() {
        _onAir = false;
        if (_event != null) _paused = true;
        _error = e.toString().replaceFirst('Exception: ', '');
        _status = _event != null
            ? 'Publish failed — event is open. Fix the issue, then Resume.'
            : _status;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pause() async {
    final stream = _stream;
    if (stream == null) return;
    setState(() => _busy = true);
    try {
      final result = await context.read<AuthState>().api.pauseStream(stream.uuid);
      await _mixer.stopPublish();
      _tick?.cancel();
      if (!mounted) return;
      setState(() {
        _stream = result.stream;
        _event = result.event ?? _event?.copyWithStatus('paused');
        _onAir = false;
        _paused = true;
        _status = 'Paused — same event stays open. Resume when ready.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _end() async {
    final stream = _stream;
    if (stream == null) return;
    setState(() => _busy = true);
    try {
      final result = await context.read<AuthState>().api.endStream(stream.uuid);
      await _mixer.stopPublish();
      _tick?.cancel();
      _liveStartedAt = null;
      if (!mounted) return;
      setState(() {
        _stream = result.stream;
        _event = null;
        _onAir = false;
        _paused = false;
        _elapsed = Duration.zero;
        _gallery = const [];
        _status = 'Standby — last live ended. Next Go live starts a new event.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _share() async {
    final org = _home?.organization;
    final url = _event?.url ?? _stream?.listenUrl ?? org?.publicChannelUrl;
    if (url == null || url.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: url));
    await Share.share(url, subject: 'Listen live on Sound Mix Live');
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Listen link copied')),
      );
    }
  }

  String? get _listenUrl =>
      _event?.url ?? _stream?.listenUrl ?? _home?.organization?.publicChannelUrl;

  Future<void> _upload() async {
    final stream = _stream;
    if (stream == null) return;
    final api = context.read<AuthState>().api;
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'],
      withData: false,
    );
    final path = result?.files.single.path;
    if (path == null) return;
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      final asset = await api.uploadLibraryAsset(
            streamUuid: stream.uuid,
            path: path,
            title: result!.files.single.name,
          );
      await _refreshLibrary();
      await _mixer.queueTrack(asset);
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = 'Uploaded & queued “${asset.title}”.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _queueAsset(LibraryAsset asset) async {
    try {
      await _mixer.queueTrack(asset);
      setState(() {
        _error = null;
        _status = 'Queued “${asset.title}”.';
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  Future<void> _selectMic(String? deviceId) async {
    if (deviceId == null) return;
    try {
      if (deviceId != 'none') {
        // Prompt at most once (first device pick). Skip if already authorized.
        final granted = await _mixer.ensureMicAccess(
          promptIfNeeded: _mixer.needsMicPermissionPrompt,
        );
        if (!granted) {
          throw Exception(
            'Microphone access is denied. Enable it in System Settings → Privacy & Security → Microphone.',
          );
        }
      }
      await _mixer.setInputDevice(deviceId);
      if (!mounted) return;
      setState(() => _error = null);
    } catch (e) {
      if (!mounted) return;
      final raw = e.toString();
      final cleaned = raw
          .replaceFirst('PlatformException(input, ', '')
          .replaceFirst('Exception: ', '')
          .replaceAll(RegExp(r', null, null\)$'), '')
          .replaceAll(RegExp(r'\)$'), '');
      setState(() => _error = cleaned);
    }
  }

  Future<void> _uploadPhoto() async {
    final stream = _stream;
    if (stream == null) return;
    if (!_galleryReady) {
      setState(() {
        _error =
            'Go live (or keep a paused event open) before posting to the service gallery.';
      });
      return;
    }
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: false,
    );
    final path = result?.files.single.path;
    if (path == null) return;
    if (!mounted) return;
    setState(() => _busy = true);
    final api = context.read<AuthState>().api;
    try {
      await api.uploadGalleryImage(
        streamUuid: stream.uuid,
        path: path,
        eventId: _openEventId,
      );
      await _refreshGallery();
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = 'Photo posted to the live gallery.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _uploadReel() async {
    final stream = _stream;
    if (stream == null) return;
    if (!_galleryReady) {
      setState(() {
        _error =
            'Go live (or keep a paused event open) before posting a reel.';
      });
      return;
    }
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['mp4', 'mov', 'webm'],
      withData: false,
    );
    final path = result?.files.single.path;
    if (path == null) return;
    if (!mounted) return;
    setState(() => _busy = true);
    final api = context.read<AuthState>().api;
    try {
      await api.uploadGalleryReel(
        streamUuid: stream.uuid,
        path: path,
        eventId: _openEventId,
      );
      await _refreshGallery();
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = 'Reel posted to the live gallery.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _uploadBackground() async {
    final stream = _stream;
    if (stream == null) return;
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: false,
    );
    final path = result?.files.single.path;
    if (path == null) return;
    if (!mounted) return;
    setState(() => _busy = true);
    final api = context.read<AuthState>().api;
    try {
      await api.uploadListenBackground(streamUuid: stream.uuid, path: path);
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = 'Listen background updated.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _deleteGalleryItem(GalleryItem item) async {
    final stream = _stream;
    if (stream == null) return;
    final label = item.isVideo ? 'reel' : 'photo';
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Remove $label?'),
        content: Text('Remove this $label from the live gallery?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await context.read<AuthState>().api.deleteGalleryItem(stream.uuid, item.id);
      await _refreshGallery();
      if (!mounted) return;
      setState(() {
        _error = null;
        _status = item.isVideo
            ? 'Reel removed from the live gallery.'
            : 'Photo removed from the live gallery.';
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final org = _home?.organization;
    final stream = _stream;

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF0E1613), StudioTheme.ink],
          ),
        ),
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: StudioTheme.accent))
            : Column(
                children: [
                  _TopBar(
                    userName: auth.user?.name ?? '',
                    orgName: org?.name,
                    streams: _home?.streams ?? const [],
                    selectedStreamUuid: stream?.uuid,
                    onSelectStream: _selectStream,
                    onAir: _onAir,
                    paused: _paused,
                    clock: _clock,
                    tab: _tab,
                    onTab: (t) => setState(() => _tab = t),
                    onLogout: () async {
                      await _mixer.stopPublish();
                      await auth.logout();
                    },
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                      child: _tab == _StudioTab.live
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                if (_error != null || _status.isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: Text(
                                      _error ?? _status,
                                      maxLines: 3,
                                      overflow: TextOverflow.ellipsis,
                                      style: GoogleFonts.outfit(
                                        fontSize: 12,
                                        color: _error != null
                                            ? StudioTheme.live
                                            : StudioTheme.mute,
                                      ),
                                    ),
                                  ),
                                _BroadcastBar(
                                  listenUrl: _listenUrl,
                                  onShare: _share,
                                  busy: _busy,
                                  onAir: _onAir,
                                  paused: _paused,
                                  canBroadcast: _home?.canBroadcast == true &&
                                      stream != null &&
                                      _mixer.isReady &&
                                      _mixer.isArmed,
                                  onGoLive: _goLive,
                                  onPause: _pause,
                                  onEnd: _end,
                                ),
                                if (_home?.organization?.isChurch == true)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 6),
                                    child: Align(
                                      alignment: Alignment.centerLeft,
                                      child: TextButton(
                                        onPressed: () => setState(
                                          () => _tab = _StudioTab.advance,
                                        ),
                                        child: Text(
                                          'Advance',
                                          style: GoogleFonts.outfit(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w600,
                                            color: StudioTheme.accentBright,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                const SizedBox(height: 12),
                                // Console + Library share one height.
                                Expanded(
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.stretch,
                                    children: [
                                      Expanded(
                                        child: ConsoleChassis(
                                          micLevel: _mixer.micLevel,
                                          playlistLevel: _mixer.playlistLevel,
                                          masterLevel: _mixer.level,
                                          micGain: _micGain,
                                          playlistGain: _playlistGain,
                                          masterGain: _masterGain,
                                          micMute: _micMute,
                                          playlistMute: _playlistMute,
                                          micCue: _micCue,
                                          playlistCue: _playlistCue,
                                          inputs: _mixer.inputs,
                                          selectedDeviceId:
                                              _mixer.selectedDeviceId,
                                          micArmed: _mixer.isArmed,
                                          busy: _busy,
                                          queueCount: _mixer.tracks.length,
                                          layoutMono: _layoutMono,
                                          outputs: _mixer.outputs,
                                          selectedOutputDeviceId:
                                              _mixer.selectedOutputDeviceId,
                                          onMicGain: (v) async {
                                            setState(() => _micGain = v);
                                            await _mixer.setGains(mic: v);
                                          },
                                          onPlaylistGain: (v) async {
                                            setState(() => _playlistGain = v);
                                            await _mixer.setGains(playlist: v);
                                          },
                                          onMasterGain: (v) async {
                                            setState(() => _masterGain = v);
                                            await _mixer.setGains(master: v);
                                          },
                                          onMicMute: () async {
                                            setState(
                                                () => _micMute = !_micMute);
                                            await _mixer
                                                .setMutes(mic: _micMute);
                                          },
                                          onPlaylistMute: () async {
                                            setState(() =>
                                                _playlistMute = !_playlistMute);
                                            await _mixer.setMutes(
                                                playlist: _playlistMute);
                                          },
                                          onMicCue: () async {
                                            setState(() => _micCue = !_micCue);
                                            await _mixer.setCues(mic: _micCue);
                                          },
                                          onPlaylistCue: () async {
                                            setState(() =>
                                                _playlistCue = !_playlistCue);
                                            await _mixer.setCues(
                                                playlist: _playlistCue);
                                          },
                                          onSelectMic: _selectMic,
                                          onEnableMic: _enableMic,
                                          showAllowMic: _mixer
                                                  .needsMicPermissionPrompt ||
                                              (_mixer.micPermission ==
                                                      'denied' &&
                                                  !_mixer.isArmed),
                                          onLayoutMono: (v) =>
                                              setState(() => _layoutMono = v),
                                          onSelectOutput: (id) async {
                                            if (id == null) return;
                                            try {
                                              await _mixer.setOutputDevice(id);
                                              if (!mounted) return;
                                              setState(() => _error = null);
                                            } catch (e) {
                                              if (!mounted) return;
                                              setState(
                                                () => _error = e
                                                    .toString()
                                                    .replaceFirst(
                                                        'Exception: ', ''),
                                              );
                                            }
                                          },
                                          onReloadDevices: () async {
                                            try {
                                              await _mixer.reloadDevices();
                                              if (!mounted) return;
                                              setState(() => _error = null);
                                            } catch (e) {
                                              if (!mounted) return;
                                              setState(
                                                () => _error = e
                                                    .toString()
                                                    .replaceFirst(
                                                        'Exception: ', ''),
                                              );
                                            }
                                          },
                                        ),
                                      ),
                                      const SizedBox(width: 16),
                                      SizedBox(
                                        width: 300,
                                        child: _MixerPanel(
                                          library: _library,
                                          tracks: _mixer.tracks,
                                          busy: _busy,
                                          onRefreshLibrary: _refreshLibrary,
                                          onUpload: _upload,
                                          onQueue: _queueAsset,
                                          onPlay: _mixer.play,
                                          onPause: _mixer.pause,
                                          onRestart: _mixer.restart,
                                          onRemove: _mixer.remove,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            )
                          : _AdvancePanel(
                              gallery: _gallery,
                              busy: _busy,
                              galleryReady: _galleryReady,
                              scriptureEnabled:
                                  _home?.organization?.isChurch == true,
                              streamUuid: _stream?.uuid,
                              api: context.read<AuthState>().api,
                              onRefresh: _refreshGallery,
                              onUploadPhoto: _uploadPhoto,
                              onUploadReel: _uploadReel,
                              onUploadBackground: _uploadBackground,
                              onDelete: _deleteGalleryItem,
                            ),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({
    required this.userName,
    required this.orgName,
    required this.streams,
    required this.selectedStreamUuid,
    required this.onSelectStream,
    required this.onAir,
    required this.paused,
    required this.clock,
    required this.tab,
    required this.onTab,
    required this.onLogout,
  });

  final String userName;
  final String? orgName;
  final List<StreamSummary> streams;
  final String? selectedStreamUuid;
  final ValueChanged<String?> onSelectStream;
  final bool onAir;
  final bool paused;
  final String clock;
  final _StudioTab tab;
  final ValueChanged<_StudioTab> onTab;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 28),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: StudioTheme.line)),
      ),
      child: Row(
        children: [
          Image.asset('assets/brand/soundmix-logo.png', height: 28),
          const SizedBox(width: 22),
          _NavTab(
            label: 'Live',
            active: tab == _StudioTab.live,
            onTap: () => onTab(_StudioTab.live),
          ),
          const SizedBox(width: 8),
          _NavTab(
            label: 'Advance',
            active: tab == _StudioTab.advance,
            onTap: () => onTab(_StudioTab.advance),
          ),
          if (streams.length > 1) ...[
            const SizedBox(width: 16),
            SizedBox(
              width: 200,
              child: DropdownButtonFormField<String>(
                value: selectedStreamUuid,
                isDense: true,
                decoration: InputDecoration(
                  isDense: true,
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  filled: true,
                  fillColor: StudioTheme.panelHi,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: StudioTheme.line),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: StudioTheme.line),
                  ),
                ),
                dropdownColor: StudioTheme.panel,
                style: GoogleFonts.outfit(
                  color: StudioTheme.cream,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
                items: streams
                    .map(
                      (s) => DropdownMenuItem(
                        value: s.uuid,
                        child: Text(
                          s.title,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: onAir ? null : onSelectStream,
              ),
            ),
          ] else if (orgName != null && orgName!.isNotEmpty) ...[
            const SizedBox(width: 16),
            Text(
              orgName!,
              style: GoogleFonts.outfit(
                color: StudioTheme.mute,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          const Spacer(),
          if (onAir || paused)
            Container(
              margin: const EdgeInsets.only(right: 16),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: onAir ? StudioTheme.liveSoft : StudioTheme.panelHi,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(
                  color: onAir
                      ? StudioTheme.live.withOpacity(0.5)
                      : StudioTheme.line,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: onAir ? StudioTheme.live : StudioTheme.mute,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    onAir ? 'ON AIR' : 'PAUSED',
                    style: GoogleFonts.outfit(
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                      letterSpacing: 1,
                      color: StudioTheme.cream,
                    ),
                  ),
                  if (onAir) ...[
                    const SizedBox(width: 10),
                    Text(
                      clock,
                      style: GoogleFonts.outfit(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        fontFeatures: const [FontFeature.tabularFigures()],
                        color: StudioTheme.live,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          Text(
            userName,
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 13),
          ),
          const SizedBox(width: 12),
          TextButton(
            onPressed: onLogout,
            style: TextButton.styleFrom(foregroundColor: StudioTheme.mute),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
  }
}

class _NavTab extends StatelessWidget {
  const _NavTab({
    required this.label,
    required this.active,
    required this.onTap,
  });

  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: active ? StudioTheme.accent.withOpacity(0.16) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: active ? StudioTheme.accent.withOpacity(0.55) : StudioTheme.line,
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.outfit(
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: active ? StudioTheme.accentBright : StudioTheme.mute,
          ),
        ),
      ),
    );
  }
}

class _MixerPanel extends StatelessWidget {
  const _MixerPanel({
    required this.library,
    required this.tracks,
    required this.busy,
    required this.onRefreshLibrary,
    required this.onUpload,
    required this.onQueue,
    required this.onPlay,
    required this.onPause,
    required this.onRestart,
    required this.onRemove,
  });

  final List<LibraryAsset> library;
  final List<MixerTrack> tracks;
  final bool busy;
  final Future<void> Function() onRefreshLibrary;
  final VoidCallback onUpload;
  final Future<void> Function(LibraryAsset) onQueue;
  final Future<void> Function(String) onPlay;
  final Future<void> Function(String) onPause;
  final Future<void> Function(String) onRestart;
  final Future<void> Function(String) onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: StudioTheme.panel.withOpacity(0.9),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: StudioTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Text(
                'Library',
                style: GoogleFonts.outfit(
                  color: StudioTheme.cream,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (tracks.isNotEmpty) ...[
                const SizedBox(width: 8),
                Text(
                  '${tracks.length} queued',
                  style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
                ),
              ],
              const Spacer(),
              IconButton(
                onPressed: busy ? null : () => onRefreshLibrary(),
                icon: const Icon(Icons.refresh_rounded, size: 18),
                color: StudioTheme.mute,
                visualDensity: VisualDensity.compact,
              ),
              TextButton(
                onPressed: busy ? null : onUpload,
                child: const Text('Upload'),
              ),
            ],
          ),
          if (tracks.isNotEmpty) ...[
            const SizedBox(height: 8),
            SizedBox(
              height: 72,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: tracks.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, i) {
                  final t = tracks[i];
                  return SizedBox(
                    width: 200,
                    child: _TrackRow(
                      track: t,
                      onPlay: () => onPlay(t.id),
                      onPause: () => onPause(t.id),
                      onRestart: () => onRestart(t.id),
                      onRemove: () => onRemove(t.id),
                    ),
                  );
                },
              ),
            ),
          ],
          const SizedBox(height: 8),
          Expanded(
            child: library.isEmpty
                ? Center(
                    child: Text(
                      'Upload audio to queue into the mix.',
                      style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 13),
                    ),
                  )
                : ListView.separated(
                    itemCount: library.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 4),
                    itemBuilder: (context, i) {
                      final a = library[i];
                      return ListTile(
                        dense: true,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                        title: Text(
                          a.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.outfit(
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                        trailing: TextButton(
                          onPressed: busy ? null : () => onQueue(a),
                          child: const Text('Queue'),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _TrackRow extends StatelessWidget {
  const _TrackRow({
    required this.track,
    required this.onPlay,
    required this.onPause,
    required this.onRestart,
    required this.onRemove,
  });

  final MixerTrack track;
  final VoidCallback onPlay;
  final VoidCallback onPause;
  final VoidCallback onRestart;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
      decoration: BoxDecoration(
        color: StudioTheme.panelHi,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: track.playing
              ? StudioTheme.accent.withOpacity(0.45)
              : StudioTheme.line,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  track.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.outfit(fontWeight: FontWeight.w700, fontSize: 13),
                ),
                Text(
                  track.ready ? (track.playing ? 'Playing' : 'Ready') : 'Loading…',
                  style: GoogleFonts.outfit(
                    color: track.playing ? StudioTheme.accentBright : StudioTheme.mute,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: track.ready ? (track.playing ? onPause : onPlay) : null,
            icon: Icon(track.playing ? Icons.pause_rounded : Icons.play_arrow_rounded),
            color: StudioTheme.accentBright,
          ),
          IconButton(
            onPressed: track.ready ? onRestart : null,
            icon: const Icon(Icons.replay_rounded, size: 20),
            color: StudioTheme.mute,
          ),
          IconButton(
            onPressed: onRemove,
            icon: const Icon(Icons.close_rounded, size: 18),
            color: StudioTheme.mute,
          ),
        ],
      ),
    );
  }
}

/// Compact transport row above Console + Library (keeps those boxes equal height).
class _BroadcastBar extends StatelessWidget {
  const _BroadcastBar({
    required this.listenUrl,
    required this.onShare,
    required this.busy,
    required this.onAir,
    required this.paused,
    required this.canBroadcast,
    required this.onGoLive,
    required this.onPause,
    required this.onEnd,
  });

  final String? listenUrl;
  final VoidCallback onShare;
  final bool busy;
  final bool onAir;
  final bool paused;
  final bool canBroadcast;
  final VoidCallback onGoLive;
  final VoidCallback onPause;
  final VoidCallback onEnd;

  @override
  Widget build(BuildContext context) {
    final goLabel = busy && !onAir
        ? (paused ? 'Resuming…' : 'Going live…')
        : onAir
            ? 'On air'
            : (paused ? 'Resume' : 'Go live');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: StudioTheme.panel.withOpacity(0.9),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: StudioTheme.line),
      ),
      child: Row(
        children: [
          FilledButton(
            onPressed: (!canBroadcast || busy || onAir) ? null : onGoLive,
            style: FilledButton.styleFrom(
              backgroundColor: onAir ? StudioTheme.live : StudioTheme.accent,
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
            ),
            child: Text(goLabel),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            onPressed: (!onAir || busy) ? null : onPause,
            style: OutlinedButton.styleFrom(
              foregroundColor: StudioTheme.cream,
              side: BorderSide(color: StudioTheme.cream.withOpacity(0.22)),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
            child: const Text('Pause'),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            onPressed: (busy || (!onAir && !paused)) ? null : onEnd,
            style: OutlinedButton.styleFrom(
              foregroundColor: StudioTheme.live,
              side: BorderSide(color: StudioTheme.live.withOpacity(0.4)),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
            child: const Text('End'),
          ),
          const Spacer(),
          Flexible(
            child: Text(
              listenUrl ?? 'No listen link',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.right,
              style: GoogleFonts.outfit(
                color: StudioTheme.mute,
                fontSize: 12,
              ),
            ),
          ),
          IconButton(
            onPressed: listenUrl == null ? null : onShare,
            tooltip: 'Copy listen link',
            icon: const Icon(Icons.link_rounded, size: 20),
            color: StudioTheme.accentBright,
          ),
        ],
      ),
    );
  }
}

class _ChurchLiveBoard extends StatefulWidget {
  const _ChurchLiveBoard({
    required this.api,
    required this.streamUuid,
  });

  final ApiClient api;
  final String streamUuid;

  @override
  State<_ChurchLiveBoard> createState() => _ChurchLiveBoardState();
}

class _ChurchLiveBoardState extends State<_ChurchLiveBoard> {
  final LiveBoardSync _liveBoard = LiveBoardSync();

  @override
  void dispose() {
    _liveBoard.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ScripturePanel(
          api: widget.api,
          streamUuid: widget.streamUuid,
          liveBoard: _liveBoard,
        ),
        const SizedBox(height: 16),
        SongPanel(
          api: widget.api,
          streamUuid: widget.streamUuid,
          liveBoard: _liveBoard,
        ),
      ],
    );
  }
}

class _AdvancePanel extends StatelessWidget {
  const _AdvancePanel({
    required this.gallery,
    required this.busy,
    required this.galleryReady,
    required this.scriptureEnabled,
    required this.streamUuid,
    required this.api,
    required this.onRefresh,
    required this.onUploadPhoto,
    required this.onUploadReel,
    required this.onUploadBackground,
    required this.onDelete,
  });

  final List<GalleryItem> gallery;
  final bool busy;
  final bool galleryReady;
  final bool scriptureEnabled;
  final String? streamUuid;
  final ApiClient api;
  final Future<void> Function() onRefresh;
  final VoidCallback onUploadPhoto;
  final VoidCallback onUploadReel;
  final VoidCallback onUploadBackground;
  final Future<void> Function(GalleryItem) onDelete;

  @override
  Widget build(BuildContext context) {
    final board = scriptureEnabled && streamUuid != null
        ? _ChurchLiveBoard(api: api, streamUuid: streamUuid!)
        : null;
    final gallerySection = _GallerySection(
      gallery: gallery,
      busy: busy,
      galleryReady: galleryReady,
      onRefresh: onRefresh,
      onUploadPhoto: onUploadPhoto,
      onUploadReel: onUploadReel,
      onUploadBackground: onUploadBackground,
      onDelete: onDelete,
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 1080 && board != null;
        if (wide) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                flex: 5,
                child: SingleChildScrollView(child: board),
              ),
              const SizedBox(width: 16),
              Expanded(flex: 6, child: gallerySection),
            ],
          );
        }
        if (board == null) {
          return gallerySection;
        }
        final galleryHeight = constraints.maxHeight.isFinite
            ? math.max(280.0, constraints.maxHeight * 0.45)
            : 320.0;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(child: SingleChildScrollView(child: board)),
            const SizedBox(height: 16),
            SizedBox(height: galleryHeight, child: gallerySection),
          ],
        );
      },
    );
  }
}

class _GallerySection extends StatelessWidget {
  const _GallerySection({
    required this.gallery,
    required this.busy,
    required this.galleryReady,
    required this.onRefresh,
    required this.onUploadPhoto,
    required this.onUploadReel,
    required this.onUploadBackground,
    required this.onDelete,
  });

  final List<GalleryItem> gallery;
  final bool busy;
  final bool galleryReady;
  final Future<void> Function() onRefresh;
  final VoidCallback onUploadPhoto;
  final VoidCallback onUploadReel;
  final VoidCallback onUploadBackground;
  final Future<void> Function(GalleryItem) onDelete;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final bounded = constraints.hasBoundedHeight;
        final grid = gallery.isEmpty
            ? Center(
                child: Text(
                  galleryReady
                      ? 'No photos or reels yet.'
                      : 'Go live to post photos and reels.',
                  style: GoogleFonts.outfit(
                    color: StudioTheme.mute,
                    fontSize: 14,
                  ),
                ),
              )
            : GridView.builder(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 4,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 0.85,
                ),
                itemCount: gallery.length,
                itemBuilder: (context, i) {
                  return _GalleryHoverTile(
                    item: gallery[i],
                    busy: busy,
                    onDelete: onDelete,
                  );
                },
              );
        return Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: StudioTheme.panel.withOpacity(0.9),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: StudioTheme.accent.withOpacity(0.2)),
          ),
          child: Column(
            mainAxisSize: bounded ? MainAxisSize.max : MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Text(
                    'SERVICE GALLERY',
                    style: GoogleFonts.outfit(
                      color: StudioTheme.mute,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    onPressed: busy ? null : () => onRefresh(),
                    icon: const Icon(Icons.refresh_rounded, size: 18),
                    color: StudioTheme.mute,
                    tooltip: 'Refresh gallery',
                  ),
                  TextButton(
                    onPressed: (busy || !galleryReady) ? null : onUploadPhoto,
                    child: const Text('Photo'),
                  ),
                  TextButton(
                    onPressed: (busy || !galleryReady) ? null : onUploadReel,
                    child: const Text('Reel'),
                  ),
                  TextButton(
                    onPressed: busy ? null : onUploadBackground,
                    child: const Text('Background'),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              if (bounded)
                Expanded(child: grid)
              else
                SizedBox(height: 280, child: grid),
            ],
          ),
        );
      },
    );
  }
}

class _GalleryHoverTile extends StatefulWidget {
  const _GalleryHoverTile({
    required this.item,
    required this.busy,
    required this.onDelete,
  });

  final GalleryItem item;
  final bool busy;
  final Future<void> Function(GalleryItem) onDelete;

  @override
  State<_GalleryHoverTile> createState() => _GalleryHoverTileState();
}

class _GalleryHoverTileState extends State<_GalleryHoverTile> {
  bool _hover = false;
  bool _focused = false;

  bool get _reveal => _hover || _focused;

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    return FocusableActionDetector(
      onShowHoverHighlight: (show) => setState(() => _hover = show),
      onShowFocusHighlight: (show) => setState(() => _focused = show),
      child: Container(
        decoration: BoxDecoration(
          color: StudioTheme.panelHi,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: StudioTheme.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (item.isVideo)
                    ColoredBox(
                      color: StudioTheme.ink,
                      child: Center(
                        child: Icon(
                          Icons.play_circle_fill_rounded,
                          color: StudioTheme.accentBright.withOpacity(0.9),
                          size: 42,
                        ),
                      ),
                    )
                  else
                    Image.network(
                      item.url,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const ColoredBox(
                        color: StudioTheme.ink,
                        child: Icon(Icons.broken_image_outlined, color: StudioTheme.mute),
                      ),
                    ),
                  if (item.isVideo)
                    Positioned(
                      left: 8,
                      top: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.black54,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          'REEL',
                          style: GoogleFonts.outfit(
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            color: StudioTheme.accentBright,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    top: 6,
                    right: 6,
                    child: AnimatedOpacity(
                      duration: const Duration(milliseconds: 120),
                      opacity: _reveal ? 1 : 0,
                      child: Material(
                        color: const Color(0xC8080C0A),
                        shape: const CircleBorder(),
                        child: IconButton(
                          tooltip: item.isVideo
                              ? 'Remove reel from gallery'
                              : 'Remove photo from gallery',
                          visualDensity: VisualDensity.compact,
                          constraints: const BoxConstraints.tightFor(
                            width: 28,
                            height: 28,
                          ),
                          padding: EdgeInsets.zero,
                          onPressed:
                              widget.busy ? null : () => widget.onDelete(item),
                          icon: const Icon(
                            Icons.close_rounded,
                            size: 16,
                            color: Color(0xFFF3D4D6),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
              child: Text(
                item.caption?.isNotEmpty == true
                    ? item.caption!
                    : (item.isVideo ? 'Video reel' : 'Photo'),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.outfit(fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
