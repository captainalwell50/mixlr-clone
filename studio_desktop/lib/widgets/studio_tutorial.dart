import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme.dart';
import 'studio_mode_cards.dart';
import 'studio_tour_targets.dart';

export 'studio_tour_targets.dart';

class StudioTourStep {
  const StudioTourStep({
    required this.title,
    required this.body,
    required this.icon,
    required this.workspace,
    this.anchor = StudioTourAnchor.none,
    this.placement = StudioTourPlacement.center,
  });

  final String title;
  final String body;
  final IconData icon;
  final StudioWorkspace workspace;
  final StudioTourAnchor anchor;
  final StudioTourPlacement placement;
}

const studioTourSteps = <StudioTourStep>[
  StudioTourStep(
    title: 'Welcome to Studio',
    body:
        'This tour names every control so a first-time operator can mix, go live, and cue the Listen page without guessing.',
    icon: Icons.waving_hand_rounded,
    workspace: StudioWorkspace.live,
  ),
  StudioTourStep(
    title: 'Live and AI & Advance',
    body:
        'These two cards switch the whole studio. Live is the mixer and broadcast. AI & Advance is scripture, song slides, and the service gallery.',
    icon: Icons.space_dashboard_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.liveAdvance,
    placement: StudioTourPlacement.below,
  ),
  StudioTourStep(
    title: 'Go live, Pause, End',
    body:
        'Go live starts publishing to the Listen page. Pause keeps the event open without sending audio. End closes the event. The link on the right is what listeners open — tap the chain icon to copy or share it.',
    icon: Icons.podcasts_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.broadcast,
    placement: StudioTourPlacement.below,
  ),
  StudioTourStep(
    title: 'Mic strip',
    body:
        'Mic is your voice. Choose SOURCE, then raise the fader until the meter moves. CUE hears the mic in headphones without changing what is on air. M mutes the mic for listeners. Allow mic appears only until macOS has granted permission.',
    icon: Icons.mic_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.mic,
    placement: StudioTourPlacement.right,
  ),
  StudioTourStep(
    title: 'Playlist strip',
    body:
        'Playlist is your queued library tracks. Queue a file, press Play, then mix it with this fader. CUE previews in headphones (use headphones to avoid feedback). M mutes songs on air. Master still controls how loud they are on Listen.',
    icon: Icons.queue_music_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.playlist,
    placement: StudioTourPlacement.right,
  ),
  StudioTourStep(
    title: 'Master strip',
    body:
        'Master is the final loudness for everyone on Listen. The 0 mark is unity, not off — all the way down is silence. MONO or STEREO is the Listen layout. HEADPHONES is your local monitor output.',
    icon: Icons.tune_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.master,
    placement: StudioTourPlacement.left,
  ),
  StudioTourStep(
    title: 'Library',
    body:
        'Library is on the right. Upload adds an audio file. Queue loads it into Playlist. Play, Pause, and Restart control the current cue. Remove takes it off the console without deleting the file from the library.',
    icon: Icons.library_music_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.library,
    placement: StudioTourPlacement.left,
  ),
  StudioTourStep(
    title: 'AI & Advance · Scripture',
    body:
        'Open the AI & Advance card. Scripture puts a Bible verse on the Listen page. Type a reference, or tap Listen for scripture and speak one (for example “John 3 16”). Prev and Next step verses. Clear removes it from Listen.',
    icon: Icons.menu_book_rounded,
    workspace: StudioWorkspace.advance,
    anchor: StudioTourAnchor.scripture,
    placement: StudioTourPlacement.right,
  ),
  StudioTourStep(
    title: 'AI & Advance · Songs',
    body:
        'Songs are lyric and announcement slides for Listen. Add a song, then cue a slide so the room can follow. This does not play audio — use Playlist on Live for that.',
    icon: Icons.lyrics_rounded,
    workspace: StudioWorkspace.advance,
    anchor: StudioTourAnchor.songs,
    placement: StudioTourPlacement.right,
  ),
  StudioTourStep(
    title: 'AI & Advance · Gallery',
    body:
        'Service Gallery posts photos, video reels, and a background image to the Listen page. Go live first, then use Photo, Reel, or Background. Listeners tap items to open them.',
    icon: Icons.photo_library_rounded,
    workspace: StudioWorkspace.advance,
    anchor: StudioTourAnchor.gallery,
    placement: StudioTourPlacement.left,
  ),
  StudioTourStep(
    title: 'You are ready',
    body:
        'Arm the mic, queue a track if you need music, then Go live. Use AI & Advance whenever the service needs verses, slides, or photos. Replay this tour anytime with Studio tour in the top bar.',
    icon: Icons.check_circle_rounded,
    workspace: StudioWorkspace.live,
    anchor: StudioTourAnchor.tourButton,
    placement: StudioTourPlacement.below,
  ),
];

const studioTourCardSize = Size(400, 236);

/// Places the explanation card next to the highlighted control.
Offset studioTourCardOffset({
  required Size view,
  required Size cardSize,
  Rect? hole,
  StudioTourPlacement placement = StudioTourPlacement.center,
}) {
  const pad = 16.0;
  double clampX(double x) =>
      x.clamp(pad, (view.width - cardSize.width - pad).clamp(pad, view.width));
  double clampY(double y) =>
      y.clamp(pad, (view.height - cardSize.height - pad).clamp(pad, view.height));

  if (hole == null || placement == StudioTourPlacement.center) {
    return Offset(
      clampX((view.width - cardSize.width) / 2),
      clampY((view.height - cardSize.height) / 2),
    );
  }

  final below = Offset(
    clampX(hole.center.dx - cardSize.width / 2),
    clampY(hole.bottom + 18),
  );
  final above = Offset(
    clampX(hole.center.dx - cardSize.width / 2),
    clampY(hole.top - 18 - cardSize.height),
  );
  final left = Offset(
    clampX(hole.left - 18 - cardSize.width),
    clampY(hole.center.dy - cardSize.height / 2),
  );
  final right = Offset(
    clampX(hole.right + 18),
    clampY(hole.center.dy - cardSize.height / 2),
  );

  switch (placement) {
    case StudioTourPlacement.below:
      if (hole.bottom + 18 + cardSize.height > view.height - pad) return above;
      return below;
    case StudioTourPlacement.above:
      if (hole.top - 18 - cardSize.height < pad) return below;
      return above;
    case StudioTourPlacement.left:
      if (hole.left - 18 - cardSize.width < pad) return right;
      return left;
    case StudioTourPlacement.right:
      if (hole.right + 18 + cardSize.width > view.width - pad) return left;
      return right;
    case StudioTourPlacement.center:
      return Offset(
        clampX((view.width - cardSize.width) / 2),
        clampY((view.height - cardSize.height) / 2),
      );
  }
}

StudioTourPlacement studioTourArrowSide({
  required Offset card,
  required Size cardSize,
  required Rect hole,
}) {
  final cardRect = card & cardSize;
  if (cardRect.top >= hole.bottom - 8) return StudioTourPlacement.above;
  if (cardRect.bottom <= hole.top + 8) return StudioTourPlacement.below;
  if (cardRect.left >= hole.right - 8) return StudioTourPlacement.left;
  return StudioTourPlacement.right;
}

/// First-run (and replayable) overlay that spotlights each Studio control.
class StudioTutorialOverlay extends StatefulWidget {
  const StudioTutorialOverlay({
    super.key,
    required this.workspace,
    required this.onWorkspace,
    required this.onFinished,
  });

  final StudioWorkspace workspace;
  final ValueChanged<StudioWorkspace> onWorkspace;
  final VoidCallback onFinished;

  @override
  State<StudioTutorialOverlay> createState() => _StudioTutorialOverlayState();
}

class _StudioTutorialOverlayState extends State<StudioTutorialOverlay> {
  int _index = 0;

  StudioTourStep get _step => studioTourSteps[_index];

  void _syncWorkspace() {
    widget.onWorkspace(_step.workspace);
  }

  void _remeasure() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final ctx = StudioTourTargets.of(_step.anchor)?.currentContext;
      if (ctx != null) {
        Scrollable.ensureVisible(ctx, alignment: 0.18, duration: Duration.zero);
      }
      setState(() {});
    });
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _syncWorkspace();
      _remeasure();
    });
  }

  void _goTo(int index) {
    setState(() => _index = index.clamp(0, studioTourSteps.length - 1));
    _syncWorkspace();
    _remeasure();
  }

  Rect? _localHole() {
    final key = StudioTourTargets.of(_step.anchor);
    final targetCtx = key?.currentContext;
    if (targetCtx == null) return null;
    final box = targetCtx.findRenderObject();
    if (box is! RenderBox || !box.hasSize || box.size.isEmpty) return null;
    final overlayBox = context.findRenderObject();
    if (overlayBox is! RenderBox) return null;
    final topLeft = overlayBox.globalToLocal(box.localToGlobal(Offset.zero));
    return topLeft & box.size;
  }

  @override
  Widget build(BuildContext context) {
    final last = _index >= studioTourSteps.length - 1;
    final hole = _localHole();
    final view = MediaQuery.sizeOf(context);
    final cardPos = studioTourCardOffset(
      view: view,
      cardSize: studioTourCardSize,
      hole: hole,
      placement: _step.placement,
    );

    return Material(
      color: Colors.transparent,
      child: Stack(
        children: [
          Positioned.fill(
            child: CustomPaint(
              key: const Key('studio-tour-spotlight'),
              painter: StudioTourSpotlightPainter(hole: hole),
            ),
          ),
          const ModalBarrier(dismissible: false, color: Colors.transparent),
          Positioned(
            left: cardPos.dx,
            top: cardPos.dy,
            width: studioTourCardSize.width,
            child: _TourCallout(
              step: _step,
              index: _index,
              last: last,
              hole: hole,
              cardOffset: cardPos,
              onSkip: widget.onFinished,
              onBack: _index > 0 ? () => _goTo(_index - 1) : null,
              onNext: last ? widget.onFinished : () => _goTo(_index + 1),
            ),
          ),
        ],
      ),
    );
  }
}

class _TourCallout extends StatelessWidget {
  const _TourCallout({
    required this.step,
    required this.index,
    required this.last,
    required this.hole,
    required this.cardOffset,
    required this.onSkip,
    required this.onBack,
    required this.onNext,
  });

  final StudioTourStep step;
  final int index;
  final bool last;
  final Rect? hole;
  final Offset cardOffset;
  final VoidCallback onSkip;
  final VoidCallback? onBack;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    final arrow = hole == null
        ? null
        : studioTourArrowSide(
            card: cardOffset,
            cardSize: studioTourCardSize,
            hole: hole!,
          );

    final card = DecoratedBox(
      decoration: BoxDecoration(
        color: StudioTheme.panel,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: StudioTheme.accent.withOpacity(0.45)),
        boxShadow: [
          BoxShadow(
            color: StudioTheme.accent.withOpacity(0.22),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: StudioTheme.accent.withOpacity(0.18),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    step.icon,
                    color: StudioTheme.accentBright,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    step.title,
                    key: const Key('studio-tour-title'),
                    style: GoogleFonts.outfit(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: StudioTheme.cream,
                    ),
                  ),
                ),
                Text(
                  '${index + 1} / ${studioTourSteps.length}',
                  style: GoogleFonts.outfit(
                    color: StudioTheme.mute,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              step.body,
              style: GoogleFonts.outfit(
                fontSize: 13.5,
                height: 1.4,
                color: const Color(0xFFE6EEEB),
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 5,
              runSpacing: 5,
              children: [
                for (var i = 0; i < studioTourSteps.length; i++)
                  Container(
                    width: i == index ? 16 : 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color: i == index
                          ? StudioTheme.accent
                          : StudioTheme.mute.withOpacity(0.45),
                      borderRadius: BorderRadius.circular(99),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                TextButton(
                  onPressed: onSkip,
                  child: const Text('Skip tour'),
                ),
                const Spacer(),
                if (onBack != null)
                  TextButton(
                    onPressed: onBack,
                    child: const Text('Back'),
                  ),
                const SizedBox(width: 8),
                FilledButton(
                  key: const Key('studio-tour-next'),
                  onPressed: onNext,
                  child: Text(last ? 'Done' : 'Next'),
                ),
              ],
            ),
          ],
        ),
      ),
    );

    Widget body = card;
    if (arrow == StudioTourPlacement.above || arrow == StudioTourPlacement.below) {
      body = Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (arrow == StudioTourPlacement.above) _TourArrow(side: arrow!),
          card,
          if (arrow == StudioTourPlacement.below) _TourArrow(side: arrow!),
        ],
      );
    } else if (arrow == StudioTourPlacement.left ||
        arrow == StudioTourPlacement.right) {
      body = Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (arrow == StudioTourPlacement.left) _TourArrow(side: arrow!),
          Flexible(child: card),
          if (arrow == StudioTourPlacement.right) _TourArrow(side: arrow!),
        ],
      );
    }

    return KeyedSubtree(
      key: const Key('studio-tour-callout'),
      child: body,
    );
  }
}

class _TourArrow extends StatelessWidget {
  const _TourArrow({required this.side});

  final StudioTourPlacement side;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      key: const Key('studio-tour-arrow'),
      size: side == StudioTourPlacement.above || side == StudioTourPlacement.below
          ? const Size(22, 12)
          : const Size(12, 22),
      painter: _TourArrowPainter(side: side),
    );
  }
}

class _TourArrowPainter extends CustomPainter {
  _TourArrowPainter({required this.side});

  final StudioTourPlacement side;

  @override
  void paint(Canvas canvas, Size size) {
    final path = Path();
    switch (side) {
      case StudioTourPlacement.above:
        path
          ..moveTo(0, size.height)
          ..lineTo(size.width / 2, 0)
          ..lineTo(size.width, size.height);
        break;
      case StudioTourPlacement.below:
        path
          ..moveTo(0, 0)
          ..lineTo(size.width / 2, size.height)
          ..lineTo(size.width, 0);
        break;
      case StudioTourPlacement.left:
        path
          ..moveTo(size.width, 0)
          ..lineTo(0, size.height / 2)
          ..lineTo(size.width, size.height);
        break;
      case StudioTourPlacement.right:
        path
          ..moveTo(0, 0)
          ..lineTo(size.width, size.height / 2)
          ..lineTo(0, size.height);
        break;
      case StudioTourPlacement.center:
        return;
    }
    path.close();
    canvas.drawPath(path, Paint()..color = StudioTheme.accent);
  }

  @override
  bool shouldRepaint(covariant _TourArrowPainter old) => side != old.side;
}

class StudioTourSpotlightPainter extends CustomPainter {
  StudioTourSpotlightPainter({required this.hole});

  final Rect? hole;

  @override
  void paint(Canvas canvas, Size size) {
    final overlay = Offset.zero & size;
    canvas.saveLayer(overlay, Paint());
    canvas.drawRect(overlay, Paint()..color = const Color(0xB3070A09));
    if (hole != null && !hole!.isEmpty) {
      final spotlight = RRect.fromRectAndRadius(
        hole!.inflate(8),
        const Radius.circular(16),
      );
      canvas.drawRRect(spotlight, Paint()..blendMode = BlendMode.clear);
    }
    canvas.restore();
    if (hole != null && !hole!.isEmpty) {
      final spotlight = RRect.fromRectAndRadius(
        hole!.inflate(8),
        const Radius.circular(16),
      );
      canvas.drawRRect(
        spotlight,
        Paint()
          ..color = StudioTheme.accent
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.5,
      );
    }
  }

  @override
  bool shouldRepaint(covariant StudioTourSpotlightPainter old) => hole != old.hole;
}
