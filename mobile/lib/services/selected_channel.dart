import 'package:flutter/foundation.dart';

/// Last channel the listener opened — shared by Listen and Live Gallery tabs.
class SelectedChannel extends ChangeNotifier {
  String? uuid;
  String? title;
  String? organization;
  String? artworkUrl;
  String? creatorType;

  bool get hasSelection => uuid != null && uuid!.isNotEmpty;
  bool get isChurch => creatorType == 'church';

  void select({
    required String uuid,
    String? title,
    String? organization,
    String? artworkUrl,
    String? creatorType,
  }) {
    final changed = this.uuid != uuid ||
        this.title != title ||
        this.organization != organization ||
        this.artworkUrl != artworkUrl ||
        this.creatorType != creatorType;
    this.uuid = uuid;
    this.title = title;
    this.organization = organization;
    this.artworkUrl = artworkUrl;
    this.creatorType = creatorType;
    if (changed) notifyListeners();
  }

  void clear() {
    if (!hasSelection) return;
    uuid = null;
    title = null;
    organization = null;
    artworkUrl = null;
    creatorType = null;
    notifyListeners();
  }
}
