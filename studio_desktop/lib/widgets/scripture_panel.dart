import 'dart:async';
import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:speech_to_text/speech_recognition_error.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:speech_to_text/speech_to_text.dart' as stt;

import '../models.dart';
import '../scripture/book_completion.dart';
import '../scripture/spoken_reference.dart';
import '../services/api_client.dart';
import '../services/live_board_sync.dart';
import '../services/mixer_bridge.dart';
import '../theme.dart';

/// speech_to_text session for "Listen for scripture".
///
/// [SpeechListenOptions.pauseFor] is omitted on purpose. The plugin's Dart
/// timer treats that as a hard stop (this app previously used 4 seconds),
/// which ended listening after a brief pause in a verse reference.
/// [SpeechListenOptions.listenFor] is also omitted so a 2-minute cap does
/// not kill the session. Apple/Android may still finalize an utterance;
/// we restart until the operator taps Stop or the panel is disposed.
@visibleForTesting
stt.SpeechListenOptions scriptureSpeechListenOptions() {
  return stt.SpeechListenOptions(
    listenMode: stt.ListenMode.dictation,
    partialResults: true,
    cancelOnError: false,
    localeId: 'en_US',
  );
}

@visibleForTesting
const scriptureMicDeniedStatus =
    'Microphone permission denied. Enable Microphone in System Settings → Privacy & Security, then tap Listen again.';

@visibleForTesting
const scriptureSpeechDeniedStatus =
    'Speech Recognition permission denied. Enable Speech Recognition in System Settings → Privacy & Security, then tap Listen again.';

@visibleForTesting
const scriptureNoAudioStatus =
    'Microphone audio isn’t reaching speech recognition. Enable the Studio microphone (SOURCE), allow Microphone in System Settings, then tap Listen.';

@visibleForTesting
const scriptureSilentMicStatus =
    'The Studio microphone is silent. Unmute SOURCE, pick the correct input, raise the fader, then speak a reference.';

@visibleForTesting
const scriptureNoWordsYetStatus =
    'Hearing the mic but no words yet. Speak a reference like “John 3 16”, or type one.';

@visibleForTesting
const scriptureHearingStatus =
    'Hearing the mic — speak a scripture reference…';

@visibleForTesting
String? scriptureListenPreflightError(String micStatus) {
  if (micStatus == 'denied') return scriptureMicDeniedStatus;
  return null;
}

@visibleForTesting
bool scriptureStatusIsDiagnostic(String status) {
  final lower = status.toLowerCase();
  return lower.contains('permission denied') ||
      lower.contains('isn’t reaching') ||
      lower.contains("isn't reaching") ||
      lower.contains('is silent') ||
      lower.contains('could not enable');
}

@visibleForTesting
String? scriptureStatusForNativeEvent(String status) {
  switch (status) {
    case 'listening':
      return 'Listening for scripture references…';
    case 'hearing':
      return scriptureHearingStatus;
    case 'notListening':
    case 'done':
      return null;
    default:
      return null;
  }
}

/// Church-only EasyWorship cue panel — mirrors web Studio Scripture controls.
class ScripturePanel extends StatefulWidget {
  const ScripturePanel({
    super.key,
    required this.api,
    required this.streamUuid,
    this.liveBoard,
    this.mixer,
  });

  final ApiClient api;
  final String streamUuid;
  final LiveBoardSync? liveBoard;
  final MixerBridge? mixer;

  @override
  State<ScripturePanel> createState() => _ScripturePanelState();
}

class _ScripturePanelState extends State<ScripturePanel> {
  final _controller = TextEditingController();
  final _speech = stt.SpeechToText();
  Timer? _suggestTimer;
  Timer? _confirmTimer;
  Timer? _restartTimer;
  List<ScriptureSuggestion> _suggestions = const [];
  int _activeSuggest = -1;
  ScriptureCue? _current;
  String _status = 'No scripture on listen';
  String? _pendingRef;
  bool _busy = false;
  bool _listening = false;
  bool _wantListen = false;
  bool _speechReady = false;
  bool _restartScheduled = false;
  String _lastHeard = '';
  String _liveTranscript = '';
  bool _liveTranscriptFinal = false;
  bool _gotResultOnce = false;
  Timer? _watchdogTimer;
  bool _applyingCompletion = false;
  int _lastTypedLength = 0;
  String _ghostSuffix = '';

  bool _mixerHeldForSpeech = false;

  @override
  void initState() {
    super.initState();
    widget.liveBoard?.addListener(_onLiveBoard);
    _hydrate();
    // Do not touch Speech APIs until the operator taps Listen. Opening
    // Advanced used to call initialize() immediately, which can kill a
    // sandboxed macOS build that lacks the speech-recognition entitlement.
  }

  void _onLiveBoard() {
    if (!mounted) return;
    if (widget.liveBoard?.mode == LiveBoardMode.song) {
      setState(() {
        _current = null;
        _status = 'No scripture on listen';
      });
    }
  }

  @override
  void dispose() {
    widget.liveBoard?.removeListener(_onLiveBoard);
    _wantListen = false;
    _suggestTimer?.cancel();
    _confirmTimer?.cancel();
    _restartTimer?.cancel();
    _watchdogTimer?.cancel();
    _unbindMixerSpeech();
    unawaited(_releaseMixerAfterSpeech());
    _controller.dispose();
    if (_speech.isListening) {
      unawaited(_speech.stop());
    }
    super.dispose();
  }

  /// Native mixer-tap SFSpeech only while Go Live — otherwise pause mixer and
  /// use speech_to_text (Aug 9 path) so two AVAudioEngines never fight.
  bool get _useMixerSpeech {
    final mixer = widget.mixer;
    if (kIsWeb || !Platform.isMacOS || mixer == null) return false;
    return mixer.publish == MixerPublishState.connected;
  }

  Future<void> _releaseMixerAfterSpeech() async {
    if (!_mixerHeldForSpeech) return;
    _mixerHeldForSpeech = false;
    final mixer = widget.mixer;
    if (mixer == null) return;
    await mixer.resumeAfterSpeechListen();
  }

  void _unbindMixerSpeech() {
    final mixer = widget.mixer;
    if (mixer == null) return;
    if (mixer.onScriptureSpeech == _onMixerSpeech) {
      mixer.onScriptureSpeech = null;
    }
    if (mixer.onScriptureSpeechStatus == _onMixerSpeechStatus) {
      mixer.onScriptureSpeechStatus = null;
    }
    if (mixer.onScriptureSpeechError == _onMixerSpeechError) {
      mixer.onScriptureSpeechError = null;
    }
    unawaited(mixer.stopScriptureListen());
  }

  void _armWatchdog() {
    _watchdogTimer?.cancel();
    _watchdogTimer = Timer(const Duration(seconds: 8), () {
      if (!mounted || !_wantListen || _gotResultOnce) return;
      // Keep a precise native diagnostic (permission / no audio / silent mic).
      if (scriptureStatusIsDiagnostic(_status)) return;
      setState(() => _status = scriptureNoWordsYetStatus);
    });
  }

  Future<void> _initSpeech() async {
    try {
      final ok = await _speech.initialize(
        onError: _onSpeechError,
        onStatus: _onSpeechStatus,
      );
      if (!mounted) return;
      setState(() {
        _speechReady = ok;
        if (!ok) {
          _status =
              'Speech recognition unavailable — allow Microphone and Speech Recognition in System Settings, or type a reference.';
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _speechReady = false;
        _status = 'Speech recognition unavailable on this Mac. Type a reference instead.';
      });
    }
  }

  void _onSpeechError(SpeechRecognitionError error) {
    if (!mounted || !_wantListen) return;
    final msg = error.errorMsg.toLowerCase();
    final permanent = error.permanent ||
        msg.contains('permission') ||
        msg.contains('not_allowed') ||
        msg.contains('disabled') ||
        msg.contains('recognizer_disabled') ||
        msg.contains('language_not_supported') ||
        msg.contains('language_unavailable') ||
        msg.contains('audio_error');

    if (permanent) {
      _wantListen = false;
      _restartTimer?.cancel();
      _watchdogTimer?.cancel();
      _watchdogTimer = null;
      unawaited(_speech.cancel());
      unawaited(_releaseMixerAfterSpeech());
      setState(() {
        _listening = false;
        _liveTranscript = '';
        _liveTranscriptFinal = false;
        _gotResultOnce = false;
        _status = _humanSpeechError(msg);
      });
      return;
    }

    // Transient: no-match / timeout / busy — keep wanting listen; status restart handles it.
    setState(() {
      if (_pendingRef == null) {
        _status = 'Listening for scripture references…';
      }
    });
  }

  String _humanSpeechError(String msg) {
    if (msg.contains('permission') || msg.contains('not_allowed')) {
      return 'Microphone or Speech Recognition permission denied. Enable both in System Settings → Privacy & Security, then try again.';
    }
    if (msg.contains('disabled') || msg.contains('recognizer')) {
      return 'Speech recognition is disabled on this Mac. Type a reference instead.';
    }
    if (msg.contains('language')) {
      return 'English (US) speech recognition is not available. Type a reference instead.';
    }
    if (msg.contains('audio')) {
      return 'Could not access the microphone for speech (it may be in use by the mixer). Type a reference, or stop the mic and try again.';
    }
    return 'Speech recognition failed. Type a reference instead.';
  }

  void _onSpeechStatus(String status) {
    if (!mounted) return;
    if (status == stt.SpeechToText.listeningStatus) {
      setState(() {
        _listening = true;
        if (_pendingRef == null && _wantListen) {
          _status = 'Listening for scripture references…';
        }
      });
      return;
    }
    if (!_wantListen) {
      setState(() => _listening = false);
      return;
    }
    if (status == stt.SpeechToText.notListeningStatus ||
        status == stt.SpeechToText.doneStatus) {
      // Engine ended (silence / utterance final / native cap). Keep the
      // operator session up and recycle, matching web Studio onend restart.
      _scheduleListenRestart();
    }
  }

  void _scheduleListenRestart() {
    if (!_wantListen || _restartScheduled) return;
    _restartScheduled = true;
    _restartTimer?.cancel();
    _restartTimer = Timer(const Duration(milliseconds: 350), () async {
      _restartScheduled = false;
      if (!mounted || !_wantListen) return;
      if (_useMixerSpeech) {
        await _restartMixerSession();
        return;
      }
      if (_speech.isListening) return;
      await _beginListenSession();
    });
  }

  Future<void> _restartMixerSession() async {
    final mixer = widget.mixer;
    if (mixer == null || !_wantListen) return;
    mixer.onScriptureSpeech = _onMixerSpeech;
    mixer.onScriptureSpeechStatus = _onMixerSpeechStatus;
    mixer.onScriptureSpeechError = _onMixerSpeechError;
    // Native already keeps the SFSpeech task alive across pauses; only re-arm
    // if the session actually stopped.
    final ok = await mixer.startScriptureListen();
    if (!mounted || !_wantListen) return;
    if (ok) {
      setState(() {
        _listening = true;
        if (_pendingRef == null && !scriptureStatusIsDiagnostic(_status)) {
          _status = 'Listening for scripture references…';
        }
      });
      return;
    }
    setState(() {
      _status = 'Could not start speech recognition. Retrying…';
    });
    _scheduleListenRestart();
  }

  Future<void> _beginListenSession() async {
    if (!_speechReady || !_wantListen) return;
    try {
      await _speech.listen(
        onResult: _onSpeechResult,
        listenOptions: scriptureSpeechListenOptions(),
      );
      if (!mounted || !_wantListen) return;
      setState(() {
        _listening = true;
        // Keep rolling transcript across silent restarts — do not clear here.
        if (_pendingRef == null) {
          _status = 'Listening for scripture references…';
        }
      });
    } catch (_) {
      if (!mounted) return;
      if (_wantListen) {
        setState(() {
          _status = 'Could not start speech recognition. Retrying…';
        });
        _scheduleListenRestart();
      } else {
        setState(() {
          _listening = false;
          _status = 'Could not start speech recognition. Type a reference instead.';
        });
      }
    }
  }

  Future<void> _hydrate() async {
    try {
      final result = await widget.api.scriptureShow(widget.streamUuid);
      if (!mounted) return;
      final mode = liveBoardModeFrom(result.liveBoard);
      final cue = mode == LiveBoardMode.song ? null : result.cue;
      setState(() {
        _current = cue;
        if (cue != null) {
          _controller.text = cue.ref;
          _lastTypedLength = cue.ref.length;
          _ghostSuffix = '';
          _status = 'Showing ${cue.ref} on listen';
        } else {
          _status = 'No scripture on listen';
        }
      });
      if (mode != null) widget.liveBoard?.apply(mode);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _status = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _status = 'Could not load scripture. Check your connection.');
    }
  }

  void _applyInlineHint(String value, {required bool expand}) {
    final hint = inferBookCompletion(value);
    final caretAtEnd = _controller.selection.baseOffset == value.length &&
        _controller.selection.extentOffset == value.length;
    if (expand &&
        hint != null &&
        hint.unique &&
        hint.expanded != value &&
        caretAtEnd) {
      _applyingCompletion = true;
      _controller.value = TextEditingValue(
        text: hint.expanded,
        selection: TextSelection.collapsed(offset: hint.expanded.length),
      );
      _applyingCompletion = false;
      _lastTypedLength = hint.expanded.length;
      _ghostSuffix = '';
      return;
    }
    final showGhost = hint != null &&
        hint.expanded != _controller.text &&
        hint.ghostSuffix.isNotEmpty;
    _ghostSuffix = showGhost ? hint.ghostSuffix : '';
  }

  bool _acceptInlineCompletion({bool requireCaretAtEnd = false}) {
    final value = _controller.text;
    if (requireCaretAtEnd && _controller.selection.extentOffset != value.length) {
      return false;
    }
    final hint = inferBookCompletion(value);
    if (hint == null || hint.expanded == value) return false;
    _applyingCompletion = true;
    _controller.value = TextEditingValue(
      text: hint.expanded,
      selection: TextSelection.collapsed(offset: hint.expanded.length),
    );
    _applyingCompletion = false;
    _lastTypedLength = hint.expanded.length;
    _ghostSuffix = '';
    setState(() {});
    _scheduleSuggest(hint.expanded);
    return true;
  }

  void _onQueryChanged(String value) {
    if (_applyingCompletion) return;
    final deleting = value.length < _lastTypedLength;
    _lastTypedLength = value.length;
    _applyInlineHint(value, expand: !deleting);
    if (mounted) setState(() {});
    _scheduleSuggest(_controller.text);
  }

  void _scheduleSuggest(String value) {
    _suggestTimer?.cancel();
    _suggestTimer = Timer(const Duration(milliseconds: 250), () async {
      final q = value.trim();
      if (q.isEmpty) {
        if (mounted) {
          setState(() {
            _suggestions = const [];
            _activeSuggest = -1;
          });
        }
        return;
      }
      try {
        final items = await widget.api.scriptureSuggest(widget.streamUuid, q);
        if (mounted) {
          setState(() {
            _suggestions = items;
            _activeSuggest = items.isEmpty ? -1 : 0;
            if (items.isEmpty && q.length >= 3) {
              _status = 'No matching verses for “$q”.';
            }
          });
        }
      } on ApiException catch (e) {
        if (mounted) setState(() => _status = e.message);
      } catch (_) {
        if (mounted) {
          setState(() => _status = 'Scripture search failed. Try again.');
        }
      }
    });
  }

  Future<void> _show([String? ref]) async {
    final target = (ref ?? _controller.text).trim();
    if (target.isEmpty) {
      setState(() => _status = 'Enter a reference like John 3:16');
      return;
    }
    setState(() {
      _busy = true;
      _status = 'Showing $target…';
    });
    try {
      final cue = await widget.api.scriptureStore(widget.streamUuid, target);
      if (!mounted) return;
      setState(() {
        _current = cue;
        _controller.text = cue.ref;
        _lastTypedLength = cue.ref.length;
        _ghostSuffix = '';
        _suggestions = const [];
        _activeSuggest = -1;
        _pendingRef = null;
        _status = 'Showing ${cue.ref} on listen';
      });
      widget.liveBoard?.markScripture();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not show that verse.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _clear() async {
    setState(() => _busy = true);
    try {
      await widget.api.scriptureClear(widget.streamUuid);
      if (!mounted) return;
      setState(() {
        _current = null;
        _status = 'Scripture cleared from listen';
      });
      widget.liveBoard?.markCleared();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _offerConfirm(String ref) {
    if (ref == _pendingRef) return;
    _confirmTimer?.cancel();
    setState(() {
      _pendingRef = ref;
      _status = 'Heard: $ref';
    });
    // Brief window to dismiss a wrong parse; keep short so listen clients see the cue fast.
    _confirmTimer = Timer(const Duration(milliseconds: 1200), () {
      if (_pendingRef == ref) {
        unawaited(_show(ref));
      }
    });
  }

  void _onSpeechResult(SpeechRecognitionResult result) {
    _handleHeardWords(result.recognizedWords, result.finalResult);
  }

  void _handleHeardWords(String rawWords, bool isFinal) {
    final words = rawWords.trim();
    // Keep the last heard line on empty partials / silent ticks — don't flash back to idle.
    if (words.isEmpty) {
      return;
    }

    _gotResultOnce = true;
    _watchdogTimer?.cancel();
    _watchdogTimer = null;

    if (mounted && _wantListen) {
      setState(() {
        _liveTranscript = words;
        _liveTranscriptFinal = isFinal;
      });
    }

    // Prefer finals; also accept partials that clearly parse (STT often lags on final).
    final ref = parseSpokenReference(words);
    if (ref == null) {
      if (isFinal && words != _lastHeard) {
        _lastHeard = words;
      }
      return;
    }
    if (!isFinal && words.length < 6) return;
    _lastHeard = words;
    _offerConfirm(ref);
  }

  void _onMixerSpeech(String words, bool isFinal) {
    _handleHeardWords(words, isFinal);
  }

  void _onMixerSpeechStatus(String status) {
    if (!mounted || !_wantListen) return;
    // Native "notListening"/"done" is handled by the Swift recycler — do not
    // bounce startScriptureListen from Dart or we cancel a live task mid-utterance.
    if (status == stt.SpeechToText.notListeningStatus ||
        status == stt.SpeechToText.doneStatus ||
        status == 'notListening' ||
        status == 'done') {
      setState(() => _listening = _wantListen);
      return;
    }
    if (status == stt.SpeechToText.listeningStatus || status == 'listening') {
      setState(() {
        _listening = true;
        if (_pendingRef == null && !scriptureStatusIsDiagnostic(_status)) {
          _status = 'Listening for scripture references…';
        }
      });
      return;
    }
    if (status == 'hearing') {
      setState(() {
        _listening = true;
        if (_pendingRef == null &&
            !_gotResultOnce &&
            !scriptureStatusIsDiagnostic(_status)) {
          _status = scriptureHearingStatus;
        }
      });
    }
  }

  void _onMixerSpeechError(String message) {
    if (!mounted || !_wantListen) return;
    final msg = message.toLowerCase();
    final permanent = msg.contains('permission') ||
        msg.contains('not authorized') ||
        msg.contains('not allowed') ||
        msg.contains('denied') ||
        msg.contains('disabled') ||
        msg.contains('restricted');
    if (permanent) {
      _wantListen = false;
      _restartTimer?.cancel();
      _restartScheduled = false;
      _watchdogTimer?.cancel();
      _watchdogTimer = null;
      _unbindMixerSpeech();
      setState(() {
        _listening = false;
        _status = message;
      });
      return;
    }
    // Keep the diagnostic visible — a silent "Listening…" hid missing mic/audio.
    setState(() => _status = message);
    // Native Swift already recycles the recognizer; avoid a second start that
    // cancels the live SFSpeech task (that was dropping transcripts).
  }

  Future<void> _toggleMixerListen() async {
    final mixer = widget.mixer;
    if (mixer == null) {
      setState(() {
        _status =
            'Speech recognition uses the Studio mixer mic. Type a reference instead.';
      });
      return;
    }
    if (_wantListen || _listening) {
      _wantListen = false;
      _restartTimer?.cancel();
      _restartScheduled = false;
      _watchdogTimer?.cancel();
      _watchdogTimer = null;
      _gotResultOnce = false;
      _unbindMixerSpeech();
      if (!mounted) return;
      setState(() {
        _listening = false;
        _liveTranscript = '';
        _liveTranscriptFinal = false;
        _status = _current != null
            ? 'Showing ${_current!.ref} on listen'
            : 'Speech recognition off';
      });
      return;
    }

    final micStatus = await mixer.refreshMicPermissionStatus();
    if (!mounted) return;
    final preflight = scriptureListenPreflightError(micStatus);
    if (preflight != null) {
      setState(() {
        _listening = false;
        _status = preflight;
      });
      return;
    }
    if (micStatus == 'notDetermined') {
      final granted = await mixer.ensureMicAccess(promptIfNeeded: true);
      if (!mounted) return;
      if (!granted) {
        setState(() {
          _listening = false;
          _status = scriptureMicDeniedStatus;
        });
        return;
      }
    }
    // Always (re)arm SOURCE so Listen never starts against a dead graph.
    try {
      await mixer.armMic();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _listening = false;
        _status =
            'Could not enable the Studio microphone. Pick a SOURCE on the mixer, then tap Listen.';
      });
      return;
    }

    mixer.onScriptureSpeech = _onMixerSpeech;
    mixer.onScriptureSpeechStatus = _onMixerSpeechStatus;
    String? startFail;
    mixer.onScriptureSpeechError = (message) {
      startFail = message;
      _onMixerSpeechError(message);
    };
    _wantListen = true;
    _gotResultOnce = false;
    setState(() {
      _listening = true;
      _liveTranscript = '';
      _liveTranscriptFinal = false;
      _status = 'Listening for scripture references…';
    });
    _armWatchdog();
    final ok = await mixer.startScriptureListen();
    if (!mounted) return;
    if (!ok) {
      _wantListen = false;
      _restartTimer?.cancel();
      _restartScheduled = false;
      _watchdogTimer?.cancel();
      setState(() {
        _listening = false;
        _status = startFail ?? scriptureSpeechDeniedStatus;
      });
    }
  }

  /// Default macOS path: pause Studio mixer, use speech_to_text (works like Aug 9).
  Future<void> _toggleSuspendedSpeechListen() async {
    final mixer = widget.mixer;
    if (_wantListen || _listening) {
      _wantListen = false;
      _restartTimer?.cancel();
      _restartScheduled = false;
      _watchdogTimer?.cancel();
      _watchdogTimer = null;
      _gotResultOnce = false;
      try {
        await _speech.stop();
      } catch (_) {}
      await _releaseMixerAfterSpeech();
      if (!mounted) return;
      setState(() {
        _listening = false;
        _liveTranscript = '';
        _liveTranscriptFinal = false;
        _status = _current != null
            ? 'Showing ${_current!.ref} on listen'
            : 'Speech recognition off';
      });
      return;
    }

    if (mixer != null) {
      final micStatus = await mixer.refreshMicPermissionStatus();
      if (!mounted) return;
      final preflight = scriptureListenPreflightError(micStatus);
      if (preflight != null) {
        setState(() {
          _listening = false;
          _status = preflight;
        });
        return;
      }
      if (micStatus == 'notDetermined') {
        final granted = await mixer.ensureMicAccess(promptIfNeeded: true);
        if (!mounted) return;
        if (!granted) {
          setState(() {
            _listening = false;
            _status = scriptureMicDeniedStatus;
          });
          return;
        }
      }
      // Arm SOURCE first (auto-selects Built-in when unset), then release it
      // so speech_to_text can open the mic without a second-engine crash.
      try {
        await mixer.armMic();
      } catch (_) {
        // Still try speech_to_text on the system default mic.
      }
      await mixer.suspendForSpeechListen();
      _mixerHeldForSpeech = true;
    }

    if (!_speechReady) {
      await _initSpeech();
    }
    if (!mounted) return;
    if (!_speechReady) {
      await _releaseMixerAfterSpeech();
      setState(() {
        _listening = false;
        _status =
            'Speech recognition unavailable — allow Microphone and Speech Recognition in System Settings, or type a reference.';
      });
      return;
    }

    _wantListen = true;
    _gotResultOnce = false;
    setState(() {
      _listening = true;
      _liveTranscript = '';
      _liveTranscriptFinal = false;
      _status = 'Listening for scripture references…';
    });
    _armWatchdog();
    await _beginListenSession();
  }

  Future<void> _toggleListen() async {
    if (Platform.isMacOS) {
      if (_useMixerSpeech) {
        await _toggleMixerListen();
      } else {
        await _toggleSuspendedSpeechListen();
      }
      return;
    }
    if (_wantListen || _listening) {
      _wantListen = false;
      _restartTimer?.cancel();
      _restartScheduled = false;
      _watchdogTimer?.cancel();
      _watchdogTimer = null;
      _gotResultOnce = false;
      await _speech.stop();
      if (!mounted) return;
      setState(() {
        _listening = false;
        _liveTranscript = '';
        _liveTranscriptFinal = false;
        _status = _current != null
            ? 'Showing ${_current!.ref} on listen'
            : 'Speech recognition off';
      });
      return;
    }

    if (!_speechReady) {
      await _initSpeech();
      if (!_speechReady) {
        setState(() {
          _status =
              'Speech recognition unavailable — allow Microphone and Speech Recognition in System Settings, or type a reference.';
        });
        return;
      }
    }

    _wantListen = true;
    _gotResultOnce = false;
    setState(() {
      _listening = true;
      _liveTranscript = '';
      _liveTranscriptFinal = false;
      _status = 'Listening for scripture references…';
    });
    _armWatchdog();
    await _beginListenSession();
  }

  Widget _livePreview(ScriptureCue cue) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
      decoration: BoxDecoration(
        color: StudioTheme.ink.withOpacity(0.72),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: StudioTheme.accent.withOpacity(0.28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'LIVE: ${cue.ref}',
            style: GoogleFonts.outfit(
              color: StudioTheme.accentBright,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.4,
            ),
          ),
          if (cue.text.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              cue.text,
              style: GoogleFonts.outfit(
                color: StudioTheme.cream,
                fontSize: 14,
                height: 1.35,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildLiveTranscript() {
    final hearing = _liveTranscript.isNotEmpty;
    final body = hearing
        ? _liveTranscript
        : (_wantListen ? 'Listening…' : 'Waiting for speech…');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: StudioTheme.ink.withOpacity(0.55),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: StudioTheme.accent.withOpacity(hearing ? 0.4 : 0.22),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Icon(
              Icons.graphic_eq,
              size: 16,
              color: _listening ? StudioTheme.accentBright : StudioTheme.mute,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  hearing ? 'Hearing…' : 'Live transcript',
                  style: GoogleFonts.outfit(
                    color: StudioTheme.accentBright,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.4,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.outfit(
                    color: hearing
                        ? (_liveTranscriptFinal
                            ? StudioTheme.cream
                            : StudioTheme.cream.withOpacity(0.82))
                        : StudioTheme.mute,
                    fontSize: 13,
                    height: 1.35,
                    fontStyle: hearing && !_liveTranscriptFinal
                        ? FontStyle.italic
                        : FontStyle.normal,
                  ),
                ),
              ],
            ),
          ),
          if (!hearing && _listening)
            Padding(
              padding: const EdgeInsets.only(left: 6, top: 2),
              child: SizedBox(
                width: 8,
                height: 14,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: StudioTheme.accent.withOpacity(0.85),
                    borderRadius: BorderRadius.circular(1),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: StudioTheme.panelHi.withOpacity(0.9),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StudioTheme.accent.withOpacity(0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'SCRIPTURE',
            style: GoogleFonts.outfit(
              color: StudioTheme.mute,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Focus(
            onKeyEvent: (node, event) {
              if (event is! KeyDownEvent) return KeyEventResult.ignored;
              if (event.logicalKey == LogicalKeyboardKey.tab) {
                if (_acceptInlineCompletion()) return KeyEventResult.handled;
                if (_suggestions.isNotEmpty) {
                  final i = _activeSuggest >= 0 ? _activeSuggest : 0;
                  _controller.text = _suggestions[i].ref;
                  _lastTypedLength = _controller.text.length;
                  _ghostSuffix = '';
                  setState(() {
                    _suggestions = const [];
                    _activeSuggest = -1;
                  });
                  return KeyEventResult.handled;
                }
              }
              if (event.logicalKey == LogicalKeyboardKey.arrowRight) {
                if (_acceptInlineCompletion(requireCaretAtEnd: true)) {
                  return KeyEventResult.handled;
                }
              }
              if (_suggestions.isEmpty) return KeyEventResult.ignored;
              if (event.logicalKey == LogicalKeyboardKey.arrowDown) {
                setState(() {
                  _activeSuggest = (_activeSuggest + 1) % _suggestions.length;
                });
                return KeyEventResult.handled;
              }
              if (event.logicalKey == LogicalKeyboardKey.arrowUp) {
                setState(() {
                  _activeSuggest = (_activeSuggest - 1 + _suggestions.length) %
                      _suggestions.length;
                });
                return KeyEventResult.handled;
              }
              if (event.logicalKey == LogicalKeyboardKey.escape) {
                setState(() {
                  _suggestions = const [];
                  _activeSuggest = -1;
                  _ghostSuffix = '';
                });
                return KeyEventResult.handled;
              }
              if (event.logicalKey == LogicalKeyboardKey.enter &&
                  _activeSuggest >= 0 &&
                  _activeSuggest < _suggestions.length) {
                unawaited(_show(_suggestions[_activeSuggest].ref));
                return KeyEventResult.handled;
              }
              return KeyEventResult.ignored;
            },
            child: Stack(
              alignment: Alignment.centerLeft,
              children: [
                TextField(
                  controller: _controller,
                  onChanged: _onQueryChanged,
                  onSubmitted: (_) {
                    if (_activeSuggest >= 0 &&
                        _activeSuggest < _suggestions.length) {
                      unawaited(_show(_suggestions[_activeSuggest].ref));
                    } else if (_suggestions.isNotEmpty) {
                      unawaited(_show(_suggestions.first.ref));
                    } else {
                      unawaited(_show());
                    }
                  },
                  style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 14),
                  decoration: InputDecoration(
                    hintText: 'e.g. John 3:16',
                    hintStyle: GoogleFonts.outfit(color: StudioTheme.mute),
                    filled: true,
                    fillColor: StudioTheme.ink.withOpacity(0.55),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: StudioTheme.line),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: StudioTheme.line),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),
                if (_ghostSuffix.isNotEmpty)
                  IgnorePointer(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      child: Text.rich(
                        TextSpan(
                          children: [
                            TextSpan(
                              text: _controller.text,
                              style: GoogleFonts.outfit(
                                color: Colors.transparent,
                                fontSize: 14,
                              ),
                            ),
                            TextSpan(
                              text: _ghostSuffix,
                              style: GoogleFonts.outfit(
                                color: StudioTheme.mute.withOpacity(0.72),
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.clip,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (_suggestions.isNotEmpty) ...[
            const SizedBox(height: 8),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 120),
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: _suggestions.length,
                separatorBuilder: (_, __) => const SizedBox(height: 6),
                itemBuilder: (context, i) {
                  final s = _suggestions[i];
                  final active = i == _activeSuggest;
                  return Material(
                    color: active
                        ? StudioTheme.accent.withOpacity(0.18)
                        : StudioTheme.ink.withOpacity(0.7),
                    borderRadius: BorderRadius.circular(10),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(10),
                      onTap: _busy ? null : () => _show(s.ref),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              s.ref,
                              style: GoogleFonts.outfit(
                                color: StudioTheme.accentBright,
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                            ),
                            if (s.preview.isNotEmpty)
                              Text(
                                s.preview,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton(
                onPressed: _busy ? null : () => _show(),
                style: FilledButton.styleFrom(backgroundColor: StudioTheme.accent),
                child: const Text('Show on listen'),
              ),
              OutlinedButton(
                onPressed: _busy ? null : _clear,
                child: const Text('Clear'),
              ),
              OutlinedButton.icon(
                onPressed: _busy ? null : _toggleListen,
                icon: Icon(_listening || _wantListen ? Icons.mic : Icons.mic_none, size: 18),
                label: Text(_listening || _wantListen ? 'Stop listening' : 'Listen for scripture'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: (_listening || _wantListen) ? StudioTheme.accentBright : null,
                ),
              ),
            ],
          ),
          if (_current != null) ...[
            const SizedBox(height: 10),
            _livePreview(_current!),
          ],
          if (_wantListen) ...[
            const SizedBox(height: 10),
            _buildLiveTranscript(),
          ],
          if (_pendingRef != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: StudioTheme.accent.withOpacity(0.12),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: StudioTheme.accent.withOpacity(0.35)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Show $_pendingRef on listen?',
                      style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 13),
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      final ref = _pendingRef!;
                      _confirmTimer?.cancel();
                      setState(() => _pendingRef = null);
                      unawaited(_show(ref));
                    },
                    child: const Text('Show'),
                  ),
                  TextButton(
                    onPressed: () {
                      _confirmTimer?.cancel();
                      setState(() => _pendingRef = null);
                    },
                    child: const Text('Dismiss'),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 8),
          Text(
            _status,
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
