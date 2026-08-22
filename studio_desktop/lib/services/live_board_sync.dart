import 'package:flutter/foundation.dart';

enum LiveBoardMode { scripture, song }

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
}
