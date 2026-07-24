import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

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
  bool _busy = false;
  bool _onAir = false;
  bool _paused = false;
  String? _status;
  String? _error;
  DateTime? _liveStartedAt;
  Timer? _tick;
  Duration _elapsed = Duration.zero;

  @override
  void initState() {
    super.initState();
    _publisher.addListener(_onPublisher);
    _prepareMic();
  }

  Future<void> _prepareMic() async {
    try {
      await _publisher.startPreview();
      if (mounted) {
        setState(() => _status = 'Mic open — check your signal meter, then go live.');
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  void _onPublisher() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _tick?.cancel();
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
      if (!mounted) return;
      HapticFeedback.selectionClick();
      setState(() {
        _onAir = false;
        _paused = true;
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

  @override
  Widget build(BuildContext context) {
    final linkColor = switch (_publisher.link) {
      PublishLink.connected => LiveMixTheme.good,
      PublishLink.connecting => LiveMixTheme.warn,
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
                : const [Color(0xFF1C1A14), LiveMixTheme.ink, Color(0xFF0A0C10)],
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
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.all(22),
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
                                  ? LiveMixTheme.live.withOpacity(0.5)
                                  : LiveMixTheme.gold.withOpacity(0.22),
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: (_onAir ? LiveMixTheme.live : LiveMixTheme.gold)
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
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 6,
                                    ),
                                    decoration: BoxDecoration(
                                      color: _onAir
                                          ? LiveMixTheme.liveSoft
                                          : LiveMixTheme.goldSoft,
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    child: Text(
                                      _onAir ? 'ON AIR' : (_paused ? 'PAUSED' : 'STANDBY'),
                                      style: TextStyle(
                                        color: _onAir
                                            ? LiveMixTheme.live
                                            : LiveMixTheme.gold,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 12,
                                        letterSpacing: 1.1,
                                      ),
                                    ),
                                  ),
                                  const Spacer(),
                                  Icon(Icons.sensors_rounded, size: 16, color: linkColor),
                                  const SizedBox(width: 6),
                                  Text(
                                    _publisher.iceState ??
                                        (_publisher.isPreviewing ? 'Mic preview' : 'Idle'),
                                    style: TextStyle(
                                      color: linkColor,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 28),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'DURATION',
                                          style: GoogleFonts.outfit(
                                            color: LiveMixTheme.mute,
                                            fontSize: 11,
                                            fontWeight: FontWeight.w800,
                                            letterSpacing: 1.3,
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        LiveDurationText(elapsed: _elapsed, fontSize: 52),
                                        const SizedBox(height: 16),
                                        Text(
                                          _status ?? 'Preparing microphone…',
                                          style: const TextStyle(
                                            color: LiveMixTheme.mute,
                                            height: 1.4,
                                            fontSize: 14,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 16),
                                  SizedBox(
                                    width: 64,
                                    child: SignalMeter(
                                      level: _publisher.level,
                                      peak: _publisher.peak,
                                      height: 180,
                                    ),
                                  ),
                                ],
                              ),
                              const Spacer(),
                              Row(
                                children: [
                                  const BrandMark(size: 28, showWordmark: false),
                                  const SizedBox(width: 10),
                                  Flexible(
                                    child: Text(
                                      '${Brand.name} Studio',
                                      style: GoogleFonts.outfit(
                                        color: LiveMixTheme.mute,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 13,
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
                        const SizedBox(height: 12),
                        Text(_error!, style: const TextStyle(color: LiveMixTheme.bad)),
                      ],
                      const SizedBox(height: 14),
                      Text(
                        PlatformInfo.isDesktop
                            ? 'Tip: speak normally and keep SIGNAL in green. Playlist & cues still live on the web Studio.'
                            : 'Tip: speak normally and keep SIGNAL in green. Full mixer stays on web Studio.',
                        style: GoogleFonts.outfit(
                          color: LiveMixTheme.mute.withOpacity(0.9),
                          fontSize: 12.5,
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
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

  Future<void> _shareChannel(OrgSummary org) async {
    await Share.share(
      'Listen live on ${org.name}: ${org.publicChannelUrl}',
      subject: org.name,
    );
  }
}
