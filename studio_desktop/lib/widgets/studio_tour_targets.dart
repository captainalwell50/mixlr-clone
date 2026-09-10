import 'package:flutter/material.dart';

enum StudioTourAnchor {
  none,
  liveAdvance,
  broadcast,
  mic,
  playlist,
  master,
  library,
  scripture,
  songs,
  gallery,
  tourButton,
}

enum StudioTourPlacement { center, below, above, left, right }

/// GlobalKeys for the first-run tour spotlight. One set for the whole Studio.
class StudioTourTargets {
  static final liveAdvance = GlobalKey(debugLabel: 'tour-liveAdvance');
  static final broadcast = GlobalKey(debugLabel: 'tour-broadcast');
  static final mic = GlobalKey(debugLabel: 'tour-mic');
  static final playlist = GlobalKey(debugLabel: 'tour-playlist');
  static final master = GlobalKey(debugLabel: 'tour-master');
  static final library = GlobalKey(debugLabel: 'tour-library');
  static final scripture = GlobalKey(debugLabel: 'tour-scripture');
  static final songs = GlobalKey(debugLabel: 'tour-songs');
  static final gallery = GlobalKey(debugLabel: 'tour-gallery');
  static final tourButton = GlobalKey(debugLabel: 'tour-button');

  static GlobalKey? of(StudioTourAnchor anchor) {
    return switch (anchor) {
      StudioTourAnchor.none => null,
      StudioTourAnchor.liveAdvance => liveAdvance,
      StudioTourAnchor.broadcast => broadcast,
      StudioTourAnchor.mic => mic,
      StudioTourAnchor.playlist => playlist,
      StudioTourAnchor.master => master,
      StudioTourAnchor.library => library,
      StudioTourAnchor.scripture => scripture,
      StudioTourAnchor.songs => songs,
      StudioTourAnchor.gallery => gallery,
      StudioTourAnchor.tourButton => tourButton,
    };
  }
}
