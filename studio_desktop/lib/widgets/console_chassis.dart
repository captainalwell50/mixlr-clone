import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models.dart';
import '../theme.dart';
import 'level_meter.dart';

/// Map linear amplitude ↔ fader position (−60 dB … +6 dB).
double _faderToGain(double fader) {
  if (fader <= 0.001) return 0;
  final db = -60 + fader * 66; // 0 → -60 dB, 1 → +6 dB
  return math.pow(10, db / 20).toDouble();
}

double _gainToFader(double gain) {
  if (gain <= 0.0001) return 0;
  final db = 20 * math.log(gain) / math.ln10;
  return ((db + 60) / 66).clamp(0.0, 1.0);
}

/// Live mixer: Mic · Playlist · Master (Mixlr-simple, no unused aux strip).
class ConsoleChassis extends StatelessWidget {
  const ConsoleChassis({
    super.key,
    required this.micLevel,
    required this.playlistLevel,
    required this.masterLevel,
    required this.micGain,
    required this.playlistGain,
    required this.masterGain,
    required this.micMute,
    required this.playlistMute,
    required this.micCue,
    required this.playlistCue,
    required this.inputs,
    required this.selectedDeviceId,
    required this.micArmed,
    required this.busy,
    required this.queueCount,
    required this.layoutMono,
    required this.outputs,
    required this.selectedOutputDeviceId,
    required this.onMicGain,
    required this.onPlaylistGain,
    required this.onMasterGain,
    required this.onMicMute,
    required this.onPlaylistMute,
    required this.onMicCue,
    required this.onPlaylistCue,
    required this.onSelectMic,
    required this.onEnableMic,
    required this.onLayoutMono,
    required this.onSelectOutput,
    required this.onReloadDevices,
    this.showAllowMic = false,
  });

  final double micLevel;
  final double playlistLevel;
  final double masterLevel;
  final double micGain;
  final double playlistGain;
  final double masterGain;
  final bool micMute;
  final bool playlistMute;
  final bool micCue;
  final bool playlistCue;
  final List<AudioInputDevice> inputs;
  final String? selectedDeviceId;
  final bool micArmed;
  final bool busy;
  final int queueCount;
  final bool layoutMono;
  final List<AudioInputDevice> outputs;
  final String? selectedOutputDeviceId;
  final ValueChanged<double> onMicGain;
  final ValueChanged<double> onPlaylistGain;
  final ValueChanged<double> onMasterGain;
  final VoidCallback onMicMute;
  final VoidCallback onPlaylistMute;
  final VoidCallback onMicCue;
  final VoidCallback onPlaylistCue;
  final ValueChanged<String?> onSelectMic;
  final VoidCallback onEnableMic;
  final ValueChanged<bool> onLayoutMono;
  final ValueChanged<String?> onSelectOutput;
  final VoidCallback onReloadDevices;
  /// Only for first-run TCC — hide once macOS has already authorized the mic.
  final bool showAllowMic;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF0A0F0D),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StudioTheme.line.withOpacity(0.85)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 14, 18, 12),
            child: Row(
              children: [
                Text(
                  'Console',
                  style: GoogleFonts.outfit(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: StudioTheme.cream,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: LevelMeter(
                    level: micMute ? 0 : micLevel,
                    peak: micMute ? 0 : micLevel,
                    bars: 28,
                    height: 18,
                  ),
                ),
                if (showAllowMic) ...[
                  const SizedBox(width: 12),
                  FilledButton(
                    onPressed: busy ? null : onEnableMic,
                    child: Text(busy ? 'Arming…' : 'Allow mic'),
                  ),
                ],
              ],
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: _Strip(
                      label: 'Mic',
                      level: micMute ? 0 : micLevel,
                      gain: micGain,
                      mute: micMute,
                      cue: micCue,
                      onGain: onMicGain,
                      onMute: onMicMute,
                      onCue: onMicCue,
                      footer: _MicSourceFooter(
                        value: selectedDeviceId,
                        inputs: inputs,
                        enabled: true,
                        onChanged: onSelectMic,
                        onReload: onReloadDevices,
                        onEnableMic: onEnableMic,
                        showAllowMic: showAllowMic,
                        busy: busy,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _Strip(
                      label: 'Playlist',
                      level: playlistMute ? 0 : playlistLevel,
                      gain: playlistGain,
                      mute: playlistMute,
                      cue: playlistCue,
                      onGain: onPlaylistGain,
                      onMute: onPlaylistMute,
                      onCue: onPlaylistCue,
                      footer: _StaticFooter(
                        label: 'QUEUE',
                        text: queueCount == 0
                            ? 'Empty'
                            : '$queueCount track${queueCount == 1 ? '' : 's'}',
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _Strip(
                      label: 'Master',
                      level: masterLevel,
                      gain: masterGain,
                      mute: false,
                      cue: false,
                      showCue: false,
                      showMute: false,
                      isMaster: true,
                      onGain: onMasterGain,
                      onMute: () {},
                      onCue: () {},
                      footer: _MasterFooter(
                        layoutMono: layoutMono,
                        onLayoutMono: onLayoutMono,
                        outputs: outputs,
                        selectedOutputDeviceId: selectedOutputDeviceId,
                        onSelectOutput: onSelectOutput,
                        outputEnabled: micArmed,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Strip extends StatelessWidget {
  const _Strip({
    required this.label,
    required this.level,
    required this.gain,
    required this.mute,
    required this.cue,
    required this.onGain,
    required this.onMute,
    required this.onCue,
    required this.footer,
    this.showCue = true,
    this.showMute = true,
    this.isMaster = false,
  });

  final String label;
  final double level;
  final double gain;
  final bool mute;
  final bool cue;
  final ValueChanged<double> onGain;
  final VoidCallback onMute;
  final VoidCallback onCue;
  final Widget footer;
  final bool showCue;
  final bool showMute;
  final bool isMaster;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
      decoration: BoxDecoration(
        color: const Color(0xFF101614),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isMaster
              ? StudioTheme.accent.withOpacity(0.45)
              : const Color(0xFF2A3330),
          width: isMaster ? 1.2 : 1,
        ),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: GoogleFonts.outfit(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: isMaster ? StudioTheme.accentBright : StudioTheme.cream,
                  ),
                ),
              ),
              if (showCue)
                _Pad(
                  label: 'CUE',
                  active: cue,
                  activeColor: StudioTheme.accentBright,
                  onTap: onCue,
                ),
              if (showCue && showMute) const SizedBox(width: 6),
              if (showMute)
                _Pad(
                  label: 'M',
                  active: mute,
                  activeColor: StudioTheme.live,
                  onTap: onMute,
                ),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _DbFader(gain: gain, onChanged: onGain)),
                const SizedBox(width: 12),
                _VerticalVu(level: level),
              ],
            ),
          ),
          const SizedBox(height: 12),
          footer,
        ],
      ),
    );
  }
}

class _VerticalVu extends StatelessWidget {
  const _VerticalVu({required this.level});
  final double level;

  @override
  Widget build(BuildContext context) {
                final lit = level.clamp(0.0, 1.0);
    return SizedBox(
      width: 14,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(3),
        child: Stack(
          alignment: Alignment.bottomCenter,
          children: [
            Container(color: StudioTheme.panelHi),
            FractionallySizedBox(
              heightFactor: lit,
              widthFactor: 1,
              child: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [
                      StudioTheme.accent,
                      StudioTheme.accentBright,
                      Color(0xFFF0B429),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DbFader extends StatelessWidget {
  const _DbFader({required this.gain, required this.onChanged});
  final double gain;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Column(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: ['+6', '0', '-10', '-20', '-∞']
              .map(
                (t) => Text(
                  t,
                  style: GoogleFonts.outfit(
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                    color: StudioTheme.mute,
                  ),
                ),
              )
              .toList(),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: RotatedBox(
            quarterTurns: 3,
            child: SliderTheme(
              data: SliderTheme.of(context).copyWith(
                trackHeight: 3,
                thumbShape: const _CapFaderThumb(),
                overlayShape: const RoundSliderOverlayShape(overlayRadius: 16),
                activeTrackColor: StudioTheme.accent.withOpacity(0.55),
                inactiveTrackColor: const Color(0xFF2A2F2D),
                thumbColor: const Color(0xFFE0E0E0),
              ),
              child: Slider(
                // dB fader: UI 0…1 → −60 dB … +6 dB → linear gain for the engine.
                value: _gainToFader(gain).clamp(0.0, 1.0),
                min: 0,
                max: 1,
                onChanged: (v) => onChanged(_faderToGain(v)),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Mixlr-style fader cap. Slider is rotated 270°, so local height becomes
/// the visible width of the knob on the vertical track.
class _CapFaderThumb extends SliderComponentShape {
  const _CapFaderThumb();

  // local: (along-track, across-track) → after RotatedBox: short×wide cap
  @override
  Size getPreferredSize(bool isEnabled, bool isDiscrete) => const Size(14, 28);

  @override
  void paint(
    PaintingContext context,
    Offset center, {
    required Animation<double> activationAnimation,
    required Animation<double> enableAnimation,
    required bool isDiscrete,
    required TextPainter labelPainter,
    required RenderBox parentBox,
    required SliderThemeData sliderTheme,
    required TextDirection textDirection,
    required double value,
    required double textScaleFactor,
    required Size sizeWithOverflow,
  }) {
    final canvas = context.canvas;
    final outer = RRect.fromRectAndRadius(
      Rect.fromCenter(center: center, width: 14, height: 28),
      const Radius.circular(3),
    );
    // Soft shadow so the cap lifts off the track.
    canvas.drawRRect(
      outer.shift(const Offset(0, 1.2)),
      Paint()..color = const Color(0x66000000),
    );
    final fill = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          Color(0xFFF4F4F4),
          Color(0xFFD0D0D0),
          Color(0xFFA8A8A8),
        ],
        stops: [0.0, 0.45, 1.0],
      ).createShader(outer.outerRect);
    canvas.drawRRect(outer, fill);
    canvas.drawRRect(
      outer,
      Paint()
        ..color = const Color(0xFF5A5A5A)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1,
    );
    // Grip lines across the cap.
    final grip = Paint()
      ..color = const Color(0x55000000)
      ..strokeWidth = 1;
    for (final dy in [-4.0, 0.0, 4.0]) {
      canvas.drawLine(
        Offset(center.dx - 3.5, center.dy + dy),
        Offset(center.dx + 3.5, center.dy + dy),
        grip,
      );
    }
    // Teal center notch for brand + position clarity.
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromCenter(center: center, width: 3, height: 10),
        const Radius.circular(1),
      ),
      Paint()..color = StudioTheme.accent,
    );
  }
}

class _Pad extends StatelessWidget {
  const _Pad({
    required this.label,
    required this.active,
    required this.activeColor,
    required this.onTap,
  });

  final String label;
  final bool active;
  final Color activeColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 26,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: active ? activeColor.withOpacity(0.2) : Colors.transparent,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
            color: active ? activeColor.withOpacity(0.85) : StudioTheme.line,
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.outfit(
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.6,
            color: active ? activeColor : StudioTheme.mute,
          ),
        ),
      ),
    );
  }
}

/// Mixlr-style mic menu: Quick start · All devices · No Input · Reload.
class _MicSourceFooter extends StatelessWidget {
  const _MicSourceFooter({
    required this.value,
    required this.inputs,
    required this.enabled,
    required this.onChanged,
    required this.onReload,
    required this.onEnableMic,
    required this.showAllowMic,
    required this.busy,
  });

  final String? value;
  final List<AudioInputDevice> inputs;
  final bool enabled;
  final ValueChanged<String?> onChanged;
  final VoidCallback onReload;
  final VoidCallback onEnableMic;
  final bool showAllowMic;
  final bool busy;

  static bool _isQuickStart(AudioInputDevice d) {
    final l = d.label.toLowerCase();
    if (d.deviceId == 'default' || d.deviceId == 'none') return false;
    return l.contains('macbook') ||
        l.contains('built-in') ||
        (l.contains('microphone') && !l.contains('airbeam'));
  }

  @override
  Widget build(BuildContext context) {
    final hardware = inputs.where((d) => d.deviceId != 'default').toList();
    final quick = hardware.where(_isQuickStart).toList();
    final selectedLabel = value == 'none' || value == null
        ? 'No Input'
        : () {
            for (final d in inputs) {
              if (d.deviceId == value) return d.label;
            }
            return 'Select source';
          }();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'SOURCE',
          style: GoogleFonts.outfit(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: StudioTheme.mute,
          ),
        ),
        const SizedBox(height: 4),
        PopupMenuButton<String>(
          enabled: enabled,
          tooltip: 'Microphone',
          color: const Color(0xFF1C1C1E),
          surfaceTintColor: Colors.transparent,
          offset: const Offset(0, 36),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
            side: const BorderSide(color: Color(0xFF3A3A3C)),
          ),
          onSelected: (id) {
            if (id == '__reload__') {
              onReload();
              return;
            }
            if (id == '__arm__') {
              onEnableMic();
              return;
            }
            onChanged(id);
          },
          itemBuilder: (context) {
            final items = <PopupMenuEntry<String>>[];
            if (quick.isNotEmpty) {
              items.add(
                PopupMenuItem<String>(
                  enabled: false,
                  height: 28,
                  child: Text(
                    'Quick start',
                    style: GoogleFonts.outfit(
                      fontSize: 11,
                      color: StudioTheme.mute,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              );
              for (final d in quick) {
                items.add(_micItem(d.deviceId, d.label, selected: value == d.deviceId));
              }
              items.add(const PopupMenuDivider(height: 8));
            }
            items.add(
              PopupMenuItem<String>(
                enabled: false,
                height: 28,
                child: Text(
                  'All devices',
                  style: GoogleFonts.outfit(
                    fontSize: 11,
                    color: StudioTheme.mute,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            );
            final all = hardware.isEmpty ? inputs : hardware;
            for (final d in all) {
              items.add(_micItem(d.deviceId, d.label, selected: value == d.deviceId));
            }
            items.add(const PopupMenuDivider(height: 8));
            items.add(
              _micItem('none', 'No Input', selected: value == 'none' || value == null),
            );
            items.add(const PopupMenuDivider(height: 8));
            items.add(
              PopupMenuItem<String>(
                value: '__reload__',
                child: Text(
                  'Reload devices',
                  style: GoogleFonts.outfit(
                    fontSize: 13,
                    color: StudioTheme.cream,
                  ),
                ),
              ),
            );
            if (showAllowMic) {
              items.add(
                PopupMenuItem<String>(
                  value: '__arm__',
                  child: Text(
                    busy ? 'Arming…' : 'Allow microphone',
                    style: GoogleFonts.outfit(
                      fontSize: 13,
                      color: StudioTheme.accentBright,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              );
            }
            return items;
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 9),
            decoration: BoxDecoration(
              color: StudioTheme.panelHi,
              borderRadius: BorderRadius.circular(6),
              border: Border.all(
                color: value != null && value != 'none'
                    ? StudioTheme.accent.withOpacity(0.55)
                    : StudioTheme.line,
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    selectedLabel,
                    style: GoogleFonts.outfit(
                      color: StudioTheme.cream,
                      fontSize: 11,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Icon(
                  Icons.expand_more,
                  size: 16,
                  color: StudioTheme.mute,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  PopupMenuItem<String> _micItem(
    String id,
    String label, {
    required bool selected,
  }) {
    return PopupMenuItem<String>(
      value: id,
      child: Row(
        children: [
          SizedBox(
            width: 18,
            child: selected
                ? Icon(Icons.check, size: 14, color: StudioTheme.accentBright)
                : null,
          ),
          Expanded(
            child: Text(
              label,
              style: GoogleFonts.outfit(
                fontSize: 13,
                color: StudioTheme.cream,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _SourceFooter extends StatelessWidget {
  const _SourceFooter({
    required this.label,
    required this.value,
    required this.inputs,
    required this.enabled,
    required this.hint,
    required this.onChanged,
  });

  final String label;
  final String? value;
  final List<AudioInputDevice> inputs;
  final bool enabled;
  final String hint;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final selected = inputs.any((d) => d.deviceId == value) ? value : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          label,
          style: GoogleFonts.outfit(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: StudioTheme.mute,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            color: StudioTheme.panelHi,
            borderRadius: BorderRadius.circular(6),
            border: Border.all(
              color: selected != null
                  ? StudioTheme.accent.withOpacity(0.55)
                  : StudioTheme.line,
            ),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              isExpanded: true,
              value: selected,
              hint: Text(
                hint,
                style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 11),
                overflow: TextOverflow.ellipsis,
              ),
              dropdownColor: StudioTheme.panelHi,
              style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 11),
              items: inputs
                  .map(
                    (d) => DropdownMenuItem(
                      value: d.deviceId,
                      child: Text(d.label, overflow: TextOverflow.ellipsis),
                    ),
                  )
                  .toList(),
              onChanged: (!enabled || inputs.isEmpty) ? null : onChanged,
            ),
          ),
        ),
      ],
    );
  }
}

class _StaticFooter extends StatelessWidget {
  const _StaticFooter({required this.label, required this.text});
  final String label;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          label,
          style: GoogleFonts.outfit(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: StudioTheme.mute,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: StudioTheme.line, style: BorderStyle.solid),
          ),
          child: Text(
            text,
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 11),
          ),
        ),
      ],
    );
  }
}

class _MasterFooter extends StatelessWidget {
  const _MasterFooter({
    required this.layoutMono,
    required this.onLayoutMono,
    required this.outputs,
    required this.selectedOutputDeviceId,
    required this.onSelectOutput,
    required this.outputEnabled,
  });

  final bool layoutMono;
  final ValueChanged<bool> onLayoutMono;
  final List<AudioInputDevice> outputs;
  final String? selectedOutputDeviceId;
  final ValueChanged<String?> onSelectOutput;
  final bool outputEnabled;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            _Pad(
              label: 'MONO',
              active: layoutMono,
              activeColor: StudioTheme.accentBright,
              onTap: () => onLayoutMono(true),
            ),
            const SizedBox(width: 6),
            _Pad(
              label: 'STEREO',
              active: !layoutMono,
              activeColor: StudioTheme.accentBright,
              onTap: () => onLayoutMono(false),
            ),
          ],
        ),
        const SizedBox(height: 8),
        _SourceFooter(
          label: 'HEADPHONES',
          value: selectedOutputDeviceId,
          inputs: outputs,
          enabled: outputEnabled,
          hint: outputEnabled ? 'Output' : 'Arm mic first',
          onChanged: onSelectOutput,
        ),
      ],
    );
  }
}

/// Mixlr-style transport footer.
class TransportBar extends StatelessWidget {
  const TransportBar({
    super.key,
    required this.onAir,
    required this.paused,
    required this.clock,
    required this.busy,
    required this.canGoLive,
    required this.onGoLive,
    required this.onPause,
    required this.onEnd,
  });

  final bool onAir;
  final bool paused;
  final String clock;
  final bool busy;
  final bool canGoLive;
  final VoidCallback onGoLive;
  final VoidCallback onPause;
  final VoidCallback onEnd;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: 18),
      decoration: BoxDecoration(
        color: StudioTheme.panel,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: StudioTheme.line),
      ),
      child: Row(
        children: [
          Text(
            onAir ? 'ON AIR' : (paused ? 'PAUSED' : 'OFF AIR'),
            style: GoogleFonts.outfit(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              letterSpacing: 1.2,
              color: onAir
                  ? StudioTheme.live
                  : (paused ? StudioTheme.accentBright : StudioTheme.cream),
            ),
          ),
          const Spacer(),
          Text(
            clock,
            style: GoogleFonts.outfit(
              fontWeight: FontWeight.w700,
              fontSize: 18,
              fontFeatures: const [FontFeature.tabularFigures()],
              color: StudioTheme.cream,
            ),
          ),
          const Spacer(),
          if (onAir) ...[
            OutlinedButton(
              onPressed: busy ? null : onPause,
              child: const Text('Pause'),
            ),
            const SizedBox(width: 8),
            FilledButton(
              onPressed: busy ? null : onEnd,
              style: FilledButton.styleFrom(backgroundColor: StudioTheme.live),
              child: const Text('End'),
            ),
          ] else
            FilledButton(
              onPressed: (busy || !canGoLive) ? null : onGoLive,
              style: FilledButton.styleFrom(
                backgroundColor: StudioTheme.live,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
              ),
              child: Text(paused ? 'Resume' : 'Start'),
            ),
        ],
      ),
    );
  }
}
