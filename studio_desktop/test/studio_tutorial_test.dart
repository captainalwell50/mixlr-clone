import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:soundmix_studio/services/studio_tutorial_store.dart';
import 'package:soundmix_studio/theme.dart';
import 'package:soundmix_studio/widgets/studio_mode_cards.dart';
import 'package:soundmix_studio/widgets/studio_tutorial.dart';

void main() {
  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('Live and Advance are large labeled cards', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StudioModeSwitcher(
            workspace: StudioWorkspace.live,
            onChanged: (_) {},
          ),
        ),
      ),
    );
    expect(find.byKey(const Key('studio-mode-live')), findsOneWidget);
    expect(find.byKey(const Key('studio-mode-advance')), findsOneWidget);
    expect(find.text('Mixer & broadcast'), findsOneWidget);
    expect(find.text('Board & gallery'), findsOneWidget);
    expect(find.text('AI & Advance'), findsOneWidget);
    final liveSize = tester.getSize(find.byKey(const Key('studio-mode-live')));
    expect(liveSize.height, greaterThanOrEqualTo(48));
    expect(liveSize.width, greaterThanOrEqualTo(180));
    final advance = tester.widget<StudioModeCard>(
      find.byKey(const Key('studio-mode-advance')),
    );
    expect(advance.accent, StudioTheme.live);
    expect(advance.glass, isTrue);
  });

  testWidgets('Advance card reports the tap', (tester) async {
    StudioWorkspace? picked;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StudioModeSwitcher(
            workspace: StudioWorkspace.live,
            onChanged: (w) => picked = w,
          ),
        ),
      ),
    );
    await tester.tap(find.byKey(const Key('studio-mode-advance')));
    expect(picked, StudioWorkspace.advance);
  });

  testWidgets('first-run tour walks Live then Advance and can finish', (tester) async {
    var workspace = StudioWorkspace.live;
    var finished = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StudioTutorialOverlay(
            workspace: workspace,
            onWorkspace: (w) => workspace = w,
            onFinished: () => finished = true,
          ),
        ),
      ),
    );
    await tester.pump();
    expect(find.text('Welcome to Studio'), findsOneWidget);
    expect(studioTourSteps.length, 11);

    for (var i = 0; i < studioTourSteps.length - 1; i++) {
      await tester.tap(find.byKey(const Key('studio-tour-next')));
      await tester.pump();
    }
    expect(find.text('You are ready'), findsOneWidget);
    expect(find.text('Done'), findsOneWidget);
    expect(
      workspace,
      StudioWorkspace.live,
      reason: 'last step returns to the Live mixer',
    );
    await tester.tap(find.byKey(const Key('studio-tour-next')));
    expect(finished, isTrue);
  });

  testWidgets('tour opens Advance on scripture step', (tester) async {
    var workspace = StudioWorkspace.live;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StudioTutorialOverlay(
            workspace: workspace,
            onWorkspace: (w) => workspace = w,
            onFinished: () {},
          ),
        ),
      ),
    );
    await tester.pump();
    // Welcome, Live cards, Go live, Mic, Playlist, Master, Library, then Scripture.
    for (var i = 0; i < 7; i++) {
      await tester.tap(find.byKey(const Key('studio-tour-next')));
      await tester.pump();
    }
    expect(find.text('AI & Advance · Scripture'), findsOneWidget);
    expect(workspace, StudioWorkspace.advance);
  });

  test('callout sits below a top control', () {
    final offset = studioTourCardOffset(
      view: const Size(1200, 800),
      cardSize: studioTourCardSize,
      hole: const Rect.fromLTWH(200, 16, 360, 58),
      placement: StudioTourPlacement.below,
    );
    expect(offset.dy, greaterThan(74));
    expect(offset.dx, greaterThan(0));
    expect(offset.dx + studioTourCardSize.width, lessThan(1200));
  });

  test('callout sits left of a right-side control', () {
    final hole = const Rect.fromLTWH(880, 120, 300, 520);
    final offset = studioTourCardOffset(
      view: const Size(1200, 800),
      cardSize: studioTourCardSize,
      hole: hole,
      placement: StudioTourPlacement.left,
    );
    expect(offset.dx + studioTourCardSize.width, lessThanOrEqualTo(hole.left));
  });

  testWidgets('tour callout points at Live and Advance cards', (tester) async {
    var workspace = StudioWorkspace.live;
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(size: Size(1200, 800)),
          child: Scaffold(
            body: SizedBox(
              width: 1200,
              height: 800,
              child: Stack(
                children: [
                  Positioned(
                    top: 20,
                    left: 80,
                    child: KeyedSubtree(
                      key: StudioTourTargets.liveAdvance,
                      child: StudioModeSwitcher(
                        workspace: workspace,
                        onChanged: (_) {},
                      ),
                    ),
                  ),
                  Positioned.fill(
                    child: StudioTutorialOverlay(
                      workspace: workspace,
                      onWorkspace: (w) => workspace = w,
                      onFinished: () {},
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.tap(find.byKey(const Key('studio-tour-next')));
    await tester.pump();
    await tester.pump();

    expect(find.text('Live and AI & Advance'), findsOneWidget);
    expect(find.byKey(const Key('studio-tour-callout')), findsOneWidget);
    expect(find.byKey(const Key('studio-tour-arrow')), findsOneWidget);

    final cards = tester.getRect(find.byKey(StudioTourTargets.liveAdvance));
    final callout = tester.getRect(find.byKey(const Key('studio-tour-callout')));
    expect(
      callout.top,
      greaterThan(cards.bottom - 12),
      reason: 'explanation should sit under the Live/Advance cards',
    );
  });

  test('tutorial store records completion', () async {
    final store = StudioTutorialStore();
    expect(await store.isCompleted(), isFalse);
    await store.markCompleted();
    expect(await store.isCompleted(), isTrue);
  });
}
