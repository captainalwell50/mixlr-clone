import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/widgets/scripture_panel.dart';

void main() {
  test('scripture listen does not end on a short silence gap', () {
    final opts = scriptureSpeechListenOptions();
    expect(opts.pauseFor, isNull);
    expect(opts.listenFor, isNull);
    expect(opts.partialResults, isTrue);
    expect(opts.cancelOnError, isFalse);
  });
}
