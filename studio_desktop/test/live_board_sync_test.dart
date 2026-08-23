import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/services/live_board_sync.dart';

void main() {
  test('live_board strings map to last-cue-wins modes', () {
    expect(liveBoardModeFrom('scripture'), LiveBoardMode.scripture);
    expect(liveBoardModeFrom('song'), LiveBoardMode.song);
    expect(liveBoardModeFrom(null), isNull);
    expect(liveBoardModeFrom(''), isNull);
  });

  test('apply notifies when the listen overlay mode changes', () {
    final board = LiveBoardSync();
    var ticks = 0;
    board.addListener(() => ticks += 1);
    board.apply(LiveBoardMode.scripture);
    board.apply(LiveBoardMode.scripture);
    board.apply(LiveBoardMode.song);
    board.markCleared();
    expect(ticks, 3);
    expect(board.mode, isNull);
  });
}
