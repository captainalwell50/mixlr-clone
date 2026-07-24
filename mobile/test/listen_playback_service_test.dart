import 'package:flutter_test/flutter_test.dart';
import 'package:live_mix/services/listen_playback_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('ListenPlaybackService is a no-op off Android and never throws', () async {
    expect(ListenPlaybackService.isActive, isFalse);

    await ListenPlaybackService.start(
      title: 'MTLH Glorious Church',
      artist: 'Sound Mix Live',
    );
    // Hosted tests are not Android — channel is not invoked.
    expect(ListenPlaybackService.isActive, isFalse);

    await ListenPlaybackService.update(
      title: 'MTLH Glorious Church',
      artist: 'Sound Mix Live',
      playing: true,
    );
    expect(ListenPlaybackService.isActive, isFalse);

    await ListenPlaybackService.stop();
    expect(ListenPlaybackService.isActive, isFalse);
  });
}
