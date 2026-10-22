import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/windows_studio_mixer.dart';

void main() {
  test('studioCleanAudioConstraints disables voice processing', () {
    final c = studioCleanAudioConstraints();
    expect(c['echoCancellation'], isFalse);
    expect(c['noiseSuppression'], isFalse);
    expect(c['autoGainControl'], isFalse);
    expect(c['sampleRate'], {'ideal': 48000});
    expect(c.containsKey('channelCount'), isFalse);
  });

  test('Auto and Stereo publish stereo; Mono does not', () {
    expect(mixLayoutPublishesStereo('auto'), isTrue);
    expect(mixLayoutPublishesStereo('stereo'), isTrue);
    expect(mixLayoutPublishesStereo('mono'), isFalse);
  });

  test('preferHighQualityOpus rewrites opus fmtp', () {
    final sdp = [
      'v=0',
      'm=audio 9 UDP/TLS/RTP/SAVPF 111',
      'a=rtpmap:111 opus/48000/2',
      'a=fmtp:111 minptime=10;useinbandfec=1',
      '',
    ].join('\n');
    final out = preferHighQualityOpus(sdp);
    expect(out, contains('maxaveragebitrate=320000'));
    expect(out, contains('stereo=0'));
    expect('a=fmtp:111'.allMatches(out).length, 1);
    expect(preferHighQualityOpus(sdp, stereo: true), contains('stereo=1'));
    expect(preferHighQualityOpus(sdp, stereo: true, bitrate: 256000), contains('maxaveragebitrate=256000'));
  });

  test('preferHighQualityOpus strips G.722 from the audio m-line', () {
    final sdp = [
      'v=0',
      'm=audio 9 UDP/TLS/RTP/SAVPF 9 111',
      'a=rtpmap:9 G722/8000',
      'a=rtpmap:111 opus/48000/2',
      'a=fmtp:111 minptime=10;useinbandfec=1',
      '',
    ].join('\n');
    final out = preferHighQualityOpus(sdp);
    expect(out, contains('m=audio 9 UDP/TLS/RTP/SAVPF 111'));
    expect(out, isNot(contains('G722')));
  });

  test('resolveWhipResource rewrites loopback MediaMTX Location onto /rtc', () {
    const whip = 'https://soundmix.live/rtc/live/abc/whip?pass=x';
    expect(
      resolveWhipResource(whip, 'http://127.0.0.1:8889/live/abc/whip/res'),
      'https://soundmix.live/rtc/live/abc/whip/res',
    );
    expect(
      resolveWhipResource(whip, '/live/abc/whip/res'),
      'https://soundmix.live/rtc/live/abc/whip/res',
    );
  });
}
