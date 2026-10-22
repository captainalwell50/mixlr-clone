import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:live_mix/main.dart';

void main() {
  testWidgets('app boots', (tester) async {
    await tester.pumpWidget(const LiveMixApp());
    await tester.pump(const Duration(milliseconds: 1500));
    expect(find.byType(LiveMixApp), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });
}
