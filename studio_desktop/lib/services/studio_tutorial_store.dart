import 'package:shared_preferences/shared_preferences.dart';

/// Remembers that the operator finished (or skipped) the first-run studio tour.
class StudioTutorialStore {
  static const completedKey = 'studio_tutorial_v1_completed';

  Future<bool> isCompleted() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(completedKey) == true;
  }

  Future<void> markCompleted() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(completedKey, true);
  }
}
