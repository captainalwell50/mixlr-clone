import 'package:flutter/foundation.dart';

enum LiveBoardMode { scripture, song }

LiveBoardMode? liveBoardModeFrom(String? raw) {
  switch (raw) {
    case 'scripture':
      return LiveBoardMode.scripture;
    case 'song':
      return LiveBoardMode.song;
    default:
      return null;
  }
}

/// Last-cued listen overlay so Studio scripture/song status labels stay in sync.
class LiveBoardSync extends ChangeNotifier {
  LiveBoardMode? mode;

  void markScripture() {
    mode = LiveBoardMode.scripture;
    notifyListeners();
  }

  void markSong() {
    mode = LiveBoardMode.song;
    notifyListeners();
  }

  void markCleared() {
    mode = null;
    notifyListeners();
  }

  void apply(LiveBoardMode? next) {
    if (mode == next) return;
    mode = next;
    notifyListeners();
  }
}
