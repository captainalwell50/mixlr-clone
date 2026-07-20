import 'package:flutter_test/flutter_test.dart';
import 'package:live_mix/main.dart';

void main() {
  testWidgets('app boots', (tester) async {
    await tester.pumpWidget(const LiveMixApp());
    await tester.pump();
    expect(find.byType(LiveMixApp), findsOneWidget);
  });
}
