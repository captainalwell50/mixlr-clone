import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/mixer_errors.dart';

void main() {
  test('does not treat MethodChannel code play as a playback failure', () {
    final error = PlatformException(
      code: 'play',
      message: 'Track not found',
    );
    expect(
      mixerOperatorError(error),
      'That track is not in the playlist. Press Queue, then Play.',
    );
  });

  test('keeps native live-play guidance', () {
    final error = PlatformException(
      code: 'play',
      message: 'Could not play that track while live. Pause, Queue again, then Play.',
    );
    expect(
      mixerOperatorError(error),
      'Could not play that track while live. Pause, Queue again, then Play.',
    );
  });

  test('maps avfaudio / -10875 without swallowing other text via play', () {
    final error = PlatformException(
      code: 'play',
      message: 'The operation could not be completed. (com.apple.coreaudio.avfaudio error -10875.)',
    );
    expect(
      mixerOperatorError(error),
      'Could not play that track while live. Pause, Queue again, then Play.',
    );
  });
}
