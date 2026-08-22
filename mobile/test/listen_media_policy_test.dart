import 'package:flutter_test/flutter_test.dart';
import 'package:live_mix/models/models.dart';
import 'package:live_mix/services/listen_media_policy.dart';

void main() {
  test('Opus-only HLS playlists are skipped for ExoPlayer', () {
    const playlist = '#EXTM3U\n'
        '#EXT-X-STREAM-INF:BANDWIDTH=128000,CODECS="opus"\n'
        'audio.m3u8\n';
    expect(hlsPlaylistLooksLikeOpusOnly(playlist), isTrue);
  });

  test('AAC / fMP4 HLS is not treated as Opus-only', () {
    const playlist = '#EXTM3U\n'
        '#EXT-X-STREAM-INF:BANDWIDTH=128000,CODECS="mp4a.40.2"\n'
        'aac.m3u8\n';
    expect(hlsPlaylistLooksLikeOpusOnly(playlist), isFalse);
  });

  test('mixed AAC + Opus master keeps HLS available', () {
    const playlist = '#EXTM3U\n'
        '#EXT-X-STREAM-INF:BANDWIDTH=96000,CODECS="opus"\n'
        'opus.m3u8\n'
        '#EXT-X-STREAM-INF:BANDWIDTH=128000,CODECS="mp4a.40.2"\n'
        'aac.m3u8\n';
    expect(hlsPlaylistLooksLikeOpusOnly(playlist), isFalse);
  });

  test('generic opus substring without a codec tag is not skipped', () {
    const playlist = '#EXTM3U\n# comment about opus encoder settings\n'
        '#EXTINF:4,\nseg.ts\n';
    expect(hlsPlaylistLooksLikeOpusOnly(playlist), isFalse);
  });

  test('cache-bust adds _sm without dropping existing query params', () {
    final busted = hlsUrlWithCacheBust(
      'https://cdn.example/live/index.m3u8?token=abc',
    );
    final uri = Uri.parse(busted);
    expect(uri.queryParameters['token'], 'abc');
    expect(uri.queryParameters['_sm'], isNotNull);
  });

  test('ListenPayload reads playback_mode, prefer_hls, and giving', () {
    final payload = ListenPayload.fromJson({
      'stream': {
        'uuid': 'fee1cf9a-e6ae-4c84-b582-150e924619ca',
        'title': 'Sunday Live',
        'status': 'live',
        'hls_url': 'https://cdn.example/index.m3u8',
        'whep_url': 'https://edge.example/whep/x',
        'playback_mode': 'hls',
        'prefer_hls': 1,
      },
      'organization': {
        'name': 'Grace',
        'creator_type': 'church',
        'logo_url': 'https://cdn.example/logo.png',
        'giving': {
          'enabled': 'true',
          'url': 'https://paystack.com/pay/grace',
        },
      },
    });

    expect(payload.preferHls, isTrue);
    expect(payload.playbackMode, 'hls');
    expect(payload.isChurch, isTrue);
    expect(payload.giving?.hasUrl, isTrue);
    expect(payload.logoUrl, contains('logo.png'));
  });

  test('GalleryItem accepts string ids and missing optional fields', () {
    final item = GalleryItem.fromJson({
      'id': '42',
      'url': 'https://cdn.example/photo.jpg',
      'type': 'image',
    });
    expect(item.id, 42);
    expect(item.isVideo, isFalse);
    expect(item.posterUrl, isNull);
  });

  test('scripture and song cues compare by content', () {
    final a = ScriptureCue(ref: 'John 3:16', text: 'For God so loved');
    final b = ScriptureCue(ref: 'John 3:16', text: 'For God so loved');
    final c = ScriptureCue(ref: 'John 3:17', text: 'For God sent');
    expect(a, equals(b));
    expect(a, isNot(equals(c)));

    final s1 = SongCue(title: 'Holy', text: 'Holy holy', slideIndex: 0);
    final s2 = SongCue(title: 'Holy', text: 'Holy holy', slideIndex: 0);
    expect(s1, equals(s2));
  });

  test('live board prefers last-cued scripture over song', () {
    final scripture = ScriptureCue(
      ref: 'John 3:16',
      text: 'For God so loved',
      updatedAt: '2026-08-22T12:00:01Z',
    );
    final song = SongCue(
      title: 'Holy',
      text: 'Holy holy',
      updatedAt: '2026-08-22T12:00:00Z',
    );
    expect(liveBoardPrefersSong(scripture, song), isFalse);
    expect(
      liveBoardPrefersSong(
        ScriptureCue(
          ref: 'John 3:16',
          text: 'For God so loved',
          updatedAt: '2026-08-22T12:00:00Z',
        ),
        SongCue(
          title: 'Holy',
          text: 'Holy holy',
          updatedAt: '2026-08-22T12:00:01Z',
        ),
      ),
      isTrue,
    );
  });
}
