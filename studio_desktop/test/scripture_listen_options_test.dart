import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/widgets/scripture_panel.dart';

void main() {
  test('scripture listen does not end on a short silence gap', () {
    final opts = scriptureSpeechListenOptions();
    expect(opts.pauseFor, isNull);
    expect(opts.listenFor, isNull);
    expect(opts.partialResults, isTrue);
    expect(opts.cancelOnError, isFalse);
    expect(opts.localeId, 'en_US');
  });

  test('denied microphone is a visible preflight error', () {
    expect(scriptureListenPreflightError('denied'), scriptureMicDeniedStatus);
    expect(scriptureListenPreflightError('authorized'), isNull);
    expect(scriptureListenPreflightError('notDetermined'), isNull);
    expect(scriptureSpeechDeniedStatus.toLowerCase(), contains('speech recognition'));
  });

  test('watchdog keeps native mic diagnostics', () {
    expect(scriptureStatusIsDiagnostic(scriptureNoAudioStatus), isTrue);
    expect(scriptureStatusIsDiagnostic(scriptureSilentMicStatus), isTrue);
    expect(scriptureStatusIsDiagnostic(scriptureSpeechDeniedStatus), isTrue);
    expect(scriptureStatusIsDiagnostic(scriptureNoWordsYetStatus), isFalse);
    expect(scriptureStatusIsDiagnostic('Listening for scripture references…'), isFalse);
  });

  test('native hearing status is distinct from permission and silence', () {
    expect(scriptureStatusForNativeEvent('listening'),
        'Listening for scripture references…');
    expect(scriptureStatusForNativeEvent('hearing'), scriptureHearingStatus);
    expect(scriptureStatusForNativeEvent('notListening'), isNull);
    expect(scriptureHearingStatus.toLowerCase(), contains('hearing'));
    expect(scriptureStatusIsDiagnostic(scriptureHearingStatus), isFalse);
  });
}
