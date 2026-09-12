import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/audio_file_extension.dart';

void main() {
  test('signed library URLs do not inherit the .live domain as an extension', () {
    const url =
        'https://soundmix.live/studio/fee1cf9a/library/12/file?expires=1&signature=ab.cd';
    expect(audioFileExtension(url: url), 'mp3');
    expect(
      audioFileExtension(url: url, filename: 'sunday-worship.wav'),
      'wav',
    );
    expect(
      audioFileExtension(url: url, mimeType: 'audio/mp4'),
      'm4a',
    );
  });

  test('local Windows paths keep a real audio extension', () {
    expect(
      audioFileExtension(url: r'C:\Users\church\Music\intro.mp3'),
      'mp3',
    );
    expect(
      audioFileExtension(filename: 'choir.FLAC'),
      'flac',
    );
  });

  test('cache ids cannot contain slashes', () {
    expect(safeTrackCacheId('asset-12'), 'asset-12');
    expect(safeTrackCacheId('a/b\\c'), 'a_b_c');
  });
}
