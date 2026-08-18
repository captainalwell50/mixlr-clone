import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:uuid/uuid.dart';

import '../brand.dart';
import '../models/models.dart';
import '../platform_info.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/network_status.dart';
import '../services/whip_publisher.dart';
import '../theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/network_banner.dart';
import '../widgets/permission_disclosure.dart';
import '../widgets/signal_meter.dart';

class GoLiveScreen extends StatefulWidget {
  const GoLiveScreen({super.key, required this.stream, this.organization});

  final StreamSummary stream;
  final OrgSummary? organization;

  @override
  State<GoLiveScreen> createState() => _GoLiveScreenState();
}

class _GoLiveScreenState extends State<GoLiveScreen> {
  final _publisher = WhipPublisher();
  final _sessionKey = const Uuid().v4();
  bool _busy = false;
  bool _onAir = false;
  bool _paused = false;
  String? _status;
  String? _error;
  DateTime? _liveStartedAt;
  Timer? _tick;
  Timer? _presenceTimer;
  Duration _elapsed = Duration.zero;
  int? _listeners;

  @override
  void initState() {
    super.initState();
    _publisher.addListener(_onPublisher);
    _prepareMic();
  }

  Future<void> _prepareMic() async {
    try {
      if (PlatformInfo.usesPermissionHandlerForMic) {
        final mic = await PermissionDisclosure.ensureMicrophone(context);
        if (!mic.isGranted) {
          if (mounted) {
            setState(() => _error =
                'Microphone permission is required to go live from Studio.');
          }
          return;
        }
      }
      await _publisher.startPreview();
      if (mounted) {
        setState(() => _status = 'Mic open — check signal, then go live.');
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  void _onPublisher() {
    if (!mounted) return;
    if (_onAir) {
      switch (_publisher.link) {
        case PublishLink.reconnecting:
          setState(() => _status = 'Network blip — republishing…');
          return;
        case PublishLink.connected:
          setState(() => _status =
              'On air — Pause keeps this event; End live closes it.');
          return;
        case PublishLink.failed:
          setState(() => _status = 'Publish link down — retrying…');
          return;
        case PublishLink.connecting:
          setState(() => _status = 'Reconnecting publish path…');
          return;
        case PublishLink.idle:
          break;
      }
    }
    setState(() {});
  }

  @override
  void dispose() {
    _tick?.cancel();
    _presenceTimer?.cancel();
    _publisher.removeListener(_onPublisher);
    _publisher.stop();
    _publisher.dispose();
    super.dispose();
  }

  void _startTimer() {
    _liveStartedAt = DateTime.now();
    _elapsed = Duration.zero;
    _tick?.cancel();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_liveStartedAt == null || !mounted) return;
      setState(() => _elapsed = DateTime.now().difference(_liveStartedAt!));
    });
  }

  void _startPresencePoll() {
    _presenceTimer?.cancel();
    unawaited(_refreshListeners());
    _presenceTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      unawaited(_refreshListeners());
    });
  }

  Future<void> _refreshListeners() async {
    if (!_onAir) return;
    try {
      final data = await context.read<AuthState>().api.presence(
            widget.stream.uuid,
            sessionKey: _sessionKey,
          );
      final raw = data['listeners'];
      if (!mounted) return;
      if (raw is int) {
        setState(() => _listeners = raw);
      } else if (raw is num) {
        setState(() => _listeners = raw.toInt());
      }
    } catch (_) {}
  }

  Future<void> _goLive() async {
    final net = context.read<NetworkStatus>();
    if (!net.hasLink) {
      setState(() => _error = 'You’re offline. Connect to the internet to go live.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _status = 'Requesting publish credentials…';
    });

    final api = context.read<AuthState>().api;
    try {
      final publish = await api.publish(widget.stream.uuid);
      if (publish.whipUrl.isEmpty) {
        throw Exception('Server did not return a WHIP URL.');
      }

      setState(() => _status = 'Connecting publish path…');
      await _publisher.start(publish.whipUrl);

      setState(() => _status = 'Marking stream live…');
      await api.goLive(widget.stream.uuid);

      HapticFeedback.mediumImpact();
      _startTimer();
      _startPresencePoll();
      setState(() {
        _onAir = true;
        _paused = false;
        _status = 'On air — Pause keeps this event; End live closes it.';
      });
    } on ApiException catch (e) {
      await _publisher.stop(keepPreview: true);
      setState(() => _error = e.message);
    } catch (e) {
      await _publisher.stop(keepPreview: true);
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pause() async {
    final api = context.read<AuthState>().api;
    setState(() {
      _busy = true;
      _error = null;
      _status = 'Pausing event…';
    });
    try {
      await api.pauseStream(widget.stream.uuid);
      await _publisher.stop(keepPreview: true);
      _tick?.cancel();
      _presenceTimer?.cancel();
      if (!mounted) return;
      HapticFeedback.selectionClick();
      setState(() {
        _onAir = false;
        _paused = true;
        _listeners = null;
        _status = 'Paused — Resume to continue this event, or End live to close it.';
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _end() async {
    final api = context.read<AuthState>().api;
    setState(() {
      _busy = true;
      _error = null;
      _status = 'Ending event…';
    });
    try {
      await _publisher.stop();
      await api.endStream(widget.stream.uuid);
      _tick?.cancel();
      _presenceTimer?.cancel();
      if (!mounted) return;
      HapticFeedback.selectionClick();
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickMic() async {
    if (_onAir) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pause first to switch microphone.')),
      );
      return;
    }
    await _publisher.refreshDevices();
    if (!mounted) return;
    final devices = _publisher.devices;
    if (devices.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No microphones found yet.')),
      );
      return;
    }

    final chosen = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: LiveMixTheme.panel,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Text(
                'Microphone',
                style: GoogleFonts.outfit(
                  color: LiveMixTheme.mist,
                  fontWeight: FontWeight.w700,
                  fontSize: 17,
                ),
              ),
            ),
            ...devices.map(
              (d) => ListTile(
                leading: Icon(
                  d.deviceId == _publisher.deviceId
                      ? Icons.check_circle_rounded
                      : Icons.mic_none_rounded,
                  color: d.deviceId == _publisher.deviceId
                      ? LiveMixTheme.accentBright
                      : LiveMixTheme.mute,
                ),
                title: Text(
                  d.label,
                  style: const TextStyle(color: LiveMixTheme.mist),
                ),
                onTap: () => Navigator.pop(ctx, d.deviceId),
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (chosen == null || !mounted) return;
    try {
      setState(() => _status = 'Switching microphone…');
      await _publisher.selectDevice(chosen);
      if (mounted) {
        setState(() => _status = 'Mic ready — ${_publisher.deviceLabel ?? 'selected'}');
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  String get _qualityLabel {
    final net = context.watch<NetworkStatus>();
    if (!net.hasLink) return 'Offline';
    if (_publisher.link == PublishLink.failed) return 'Link failed';
    if (_publisher.link == PublishLink.connecting) return 'Connecting';
    if (_publisher.link == PublishLink.connected) {
      if (net.health == NetHealth.degraded) return 'Degraded';
      return 'Good';
    }
    if (net.health == NetHealth.degraded) return 'No server';
    return _publisher.isPreviewing ? 'Preview' : 'Standby';
  }

  Color get _qualityColor {
    switch (_qualityLabel) {
      case 'Good':
        return LiveMixTheme.good;
      case 'Connecting':
      case 'Preview':
      case 'Standby':
        return LiveMixTheme.warn;
      case 'Degraded':
      case 'No server':
        return LiveMixTheme.warn;
      default:
        return LiveMixTheme.bad;
    }
  }

  @override
  Widget build(BuildContext context) {
    final linkColor = switch (_publisher.link) {
      PublishLink.connected => LiveMixTheme.good,
      PublishLink.connecting => LiveMixTheme.warn,
      PublishLink.reconnecting => LiveMixTheme.warn,
      PublishLink.failed => LiveMixTheme.bad,
      PublishLink.idle => LiveMixTheme.mute,
    };

    final org = widget.organization;
    final title = widget.stream.title.length > 22
        ? '${widget.stream.title.substring(0, 20)}…'
        : widget.stream.title;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: _onAir
                ? const [Color(0xFF2A1416), LiveMixTheme.ink, Color(0xFF0A0C10)]
                : const [Color(0xFF12352F), LiveMixTheme.ink, Color(0xFF0A0C10)],
          ),
        ),
        child: Column(
          children: [
            SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(4, 0, 8, 0),
                child: Row(
                  children: [
                    IconButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      icon: const Icon(Icons.arrow_back_rounded),
                    ),
                    const BrandMark(size: 28, showWordmark: false),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            Brand.name,
                            style: GoogleFonts.outfit(
                              color: LiveMixTheme.mist,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.2,
                              height: 1.1,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            title,
                            style: GoogleFonts.outfit(
                              color: LiveMixTheme.mute,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              height: 1.2,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    if (org != null)
                      IconButton(
                        tooltip: 'Share channel',
                        onPressed: () => _shareChannel(org),
                        icon: const Icon(Icons.ios_share_rounded),
                      ),
                    const NetworkPill(),
                  ],
                ),
              ),
            ),
            const NetworkBanner(),
            Expanded(
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(28),
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                LiveMixTheme.panelHi,
                                LiveMixTheme.panel,
                              ],
                            ),
                            border: Border.all(
                              color: _onAir
                                  ? LiveMixTheme.live.withOpacity(0.55)
                                  : LiveMixTheme.accent.withOpacity(0.28),
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: (_onAir ? LiveMixTheme.live : LiveMixTheme.accent)
                                    .withOpacity(0.14),
                                blurRadius: 36,
                                offset: const Offset(0, 16),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  _StatusChip(
                                    label: _onAir
                                        ? 'ON AIR'
                                        : (_paused ? 'PAUSED' : 'STANDBY'),
                                    color: _onAir
                                        ? LiveMixTheme.live
                                        : LiveMixTheme.accent,
                                    soft: _onAir
                                        ? LiveMixTheme.liveSoft
                                        : LiveMixTheme.accentSoft,
                                  ),
                                  const Spacer(),
                                  Icon(Icons.sensors_rounded, size: 16, color: linkColor),
                                  const SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      _publisher.iceState ??
                                          (_publisher.isPreviewing
                                              ? 'Mic preview'
                                              : 'Idle'),
                                      style: TextStyle(
                                        color: linkColor,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              Row(
                                children: [
                                  _StatPill(
                                    icon: Icons.timer_outlined,
                                    label: 'TIME',
                                    value: _formatElapsed(_elapsed),
                                  ),
                                  const SizedBox(width: 8),
                                  _StatPill(
                                    icon: Icons.people_outline_rounded,
                                    label: 'LISTENERS',
                                    value: _onAir
                                        ? (_listeners?.toString() ?? '—')
                                        : '—',
                                  ),
                                  const SizedBox(width: 8),
                                  _StatPill(
                                    icon: Icons.network_check_rounded,
                                    label: 'QUALITY',
                                    value: _qualityLabel,
                                    valueColor: _qualityColor,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 18),
                              Expanded(
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            'MIC CHANNEL',
                                            style: GoogleFonts.outfit(
                                              color: LiveMixTheme.mute,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w800,
                                              letterSpacing: 1.2,
                                            ),
                                          ),
                                          const SizedBox(height: 10),
                                          Expanded(
                                            child: _MicStrip(
                                              level: _publisher.level,
                                              muted: _publisher.isMuted,
                                              deviceLabel:
                                                  _publisher.deviceLabel ?? 'Microphone',
                                              onMuteToggle: () => _publisher
                                                  .setMuted(!_publisher.isMuted),
                                              onPickMic: _busy ? null : _pickMic,
                                              canPick: !_onAir,
                                            ),
                                          ),
                                          const SizedBox(height: 10),
                                          Text(
                                            _status ?? 'Preparing microphone…',
                                            style: const TextStyle(
                                              color: LiveMixTheme.mute,
                                              height: 1.35,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 14),
                                    SizedBox(
                                      width: 72,
                                      child: SignalMeter(
                                        level: _publisher.isMuted
                                            ? 0
                                            : _publisher.level,
                                        peak: _publisher.isMuted
                                            ? 0
                                            : _publisher.peak,
                                        height: 200,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  const BrandMark(size: 24, showWordmark: false),
                                  const SizedBox(width: 8),
                                  Flexible(
                                    child: Text(
                                      '${Brand.name} Studio · single mic',
                                      style: GoogleFonts.outfit(
                                        color: LiveMixTheme.mute,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 12,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 10),
                        Text(_error!, style: const TextStyle(color: LiveMixTheme.bad)),
                      ],
                      const SizedBox(height: 10),
                      Text(
                        PlatformInfo.isDesktop
                            ? 'Full playlist mixer stays on the web Studio.'
                            : 'Full mixer (music/guests/cues) stays on web Studio.',
                        style: GoogleFonts.outfit(
                          color: LiveMixTheme.mute.withOpacity(0.9),
                          fontSize: 12,
                          height: 1.35,
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (!_onAir) ...[
                        FilledButton.icon(
                          onPressed: _busy ? null : _goLive,
                          icon: const Icon(Icons.podcasts_rounded),
                          label: Text(
                            _busy
                                ? 'Connecting…'
                                : (_paused ? 'Resume' : 'Go live'),
                          ),
                        ),
                        if (_paused) ...[
                          const SizedBox(height: 8),
                          OutlinedButton.icon(
                            onPressed: _busy ? null : _end,
                            icon: const Icon(Icons.stop_rounded),
                            label: const Text('End live'),
                          ),
                        ],
                      ] else ...[
                        OutlinedButton.icon(
                          onPressed: _busy ? null : _pause,
                          icon: const Icon(Icons.pause_rounded),
                          label: Text(_busy ? 'Working…' : 'Pause'),
                        ),
                        const SizedBox(height: 8),
                        FilledButton.icon(
                          style: FilledButton.styleFrom(
                            backgroundColor: LiveMixTheme.live,
                            foregroundColor: Colors.white,
                          ),
                          onPressed: _busy ? null : _end,
                          icon: const Icon(Icons.stop_rounded),
                          label: Text(_busy ? 'Ending…' : 'End live'),
                        ),
                      ],
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

  String _formatElapsed(Duration elapsed) {
    final h = elapsed.inHours;
    final m = elapsed.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');
    if (h > 0) return '$h:$m:$s';
    return '$m:$s';
  }

  Future<void> _shareChannel(OrgSummary org) async {
    await Share.share(
      'Listen live on ${org.name}: ${org.publicChannelUrl}',
      subject: org.name,
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.color,
    required this.soft,
  });

  final String label;
  final Color color;
  final Color soft;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: soft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withOpacity(0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w800,
              fontSize: 12,
              letterSpacing: 1.1,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatPill extends StatelessWidget {
  const _StatPill({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: LiveMixTheme.ink.withOpacity(0.45),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: LiveMixTheme.mist.withOpacity(0.08)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 13, color: LiveMixTheme.mute),
                const SizedBox(width: 4),
                Flexible(
                  child: Text(
                    label,
                    style: GoogleFonts.outfit(
                      color: LiveMixTheme.mute,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: GoogleFonts.outfit(
                color: valueColor ?? LiveMixTheme.mist,
                fontSize: 15,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.2,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

class _MicStrip extends StatelessWidget {
  const _MicStrip({
    required this.level,
    required this.muted,
    required this.deviceLabel,
    required this.onMuteToggle,
    required this.onPickMic,
    required this.canPick,
  });

  final double level;
  final bool muted;
  final String deviceLabel;
  final VoidCallback onMuteToggle;
  final VoidCallback? onPickMic;
  final bool canPick;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: LiveMixTheme.ink.withOpacity(0.4),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: muted
              ? LiveMixTheme.bad.withOpacity(0.35)
              : LiveMixTheme.accent.withOpacity(0.22),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                muted ? Icons.mic_off_rounded : Icons.mic_rounded,
                color: muted ? LiveMixTheme.bad : LiveMixTheme.accentBright,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  muted ? 'Muted' : 'Mic live path',
                  style: GoogleFonts.outfit(
                    color: LiveMixTheme.mist,
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
              ),
              IconButton(
                tooltip: muted ? 'Unmute' : 'Mute',
                onPressed: onMuteToggle,
                icon: Icon(
                  muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                  color: muted ? LiveMixTheme.bad : LiveMixTheme.mist,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            deviceLabel,
            style: const TextStyle(color: LiveMixTheme.mute, fontSize: 12.5),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const Spacer(),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: muted ? 0 : level.clamp(0.0, 1.0),
              minHeight: 10,
              backgroundColor: LiveMixTheme.panelHi,
              color: muted
                  ? LiveMixTheme.mute
                  : (level > 0.9 ? LiveMixTheme.bad : LiveMixTheme.good),
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: canPick ? onPickMic : null,
            icon: const Icon(Icons.settings_voice_rounded, size: 18),
            label: Text(canPick ? 'Choose mic' : 'Mic locked on air'),
          ),
        ],
      ),
    );
  }
}
