import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/playlist_queue_store.dart';

void main() {
  test('round-trips queue entries per stream', () async {
    final memory = <String, String>{};
    final store = PlaylistQueueStore(memory: memory, fileExists: (_) => true);
    final entries = [
      const PlaylistQueueEntry(
        id: 'asset-1',
        title: 'Praise',
        url: 'https://example.com/praise.mp3',
        assetId: 1,
        localPath: '/cache/praise.mp3',
      ),
      const PlaylistQueueEntry(
        id: 'asset-2',
        title: 'Worship',
        url: 'https://example.com/worship.mp3',
        assetId: 2,
      ),
    ];

    await store.save('stream-a', entries);
    final loaded = await store.load('stream-a');

    expect(loaded, hasLength(2));
    expect(loaded.first.id, 'asset-1');
    expect(loaded.first.localPath, '/cache/praise.mp3');
    expect(loaded.last.title, 'Worship');
    expect(await store.load('stream-b'), isEmpty);
  });

  test('usable keeps cached files and remote URLs, drops missing local-only', () {
    final store = PlaylistQueueStore(
      memory: {},
      fileExists: (path) => path == '/cache/keep.mp3',
    );
    final usable = store.usable(const [
      PlaylistQueueEntry(
        id: 'keep',
        title: 'Keep',
        url: '',
        localPath: '/cache/keep.mp3',
      ),
      PlaylistQueueEntry(
        id: 'gone',
        title: 'Gone',
        url: '',
        localPath: '/cache/gone.mp3',
      ),
      PlaylistQueueEntry(
        id: 'remote',
        title: 'Remote',
        url: 'https://example.com/remote.mp3',
        localPath: '/cache/gone.mp3',
      ),
    ]);

    expect(usable.map((e) => e.id), ['keep', 'remote']);
  });

  test('ignores corrupt saved JSON', () async {
    final memory = {
      '${PlaylistQueueStore.prefix}bad': '{not-json',
    };
    final store = PlaylistQueueStore(memory: memory, fileExists: (_) => false);
    expect(await store.load('bad'), isEmpty);
  });
}
