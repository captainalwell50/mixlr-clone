import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/windows_studio_mixer.dart';

void main() {
  test('preferHighQualityOpus rewrites opus fmtp', () {
    final sdp = [
      'v=0',
      'm=audio 9 UDP/TLS/RTP/SAVPF 111',
      'a=rtpmap:111 opus/48000/2',
      'a=fmtp:111 minptime=10;useinbandfec=1',
      '',
    ].join('\n');
    final out = preferHighQualityOpus(sdp);
    expect(out, contains('maxaveragebitrate=510000'));
    expect(out, contains('stereo=1'));
    expect('a=fmtp:111'.allMatches(out).length, 1);
  });
}
