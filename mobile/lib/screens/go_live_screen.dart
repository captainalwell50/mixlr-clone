import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/network_status.dart';
import '../services/whip_publisher.dart';
import '../theme.dart';
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
        _status = 'On air — listeners can join now.';
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

  Future<void> _end() async {
    final api = context.read<AuthState>().api;
    setState(() {
      _busy = true;
      _error = null;
      _status = 'Ending…';
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

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.stream.title),
        actions: [
          if (org != null)
            IconButton(
              tooltip: 'Share channel',
              onPressed: () => _shareChannel(org),
              icon: const Icon(Icons.ios_share_rounded),
            ),
          const Padding(
            padding: EdgeInsets.only(right: 12),
            child: NetworkPill(),
          ),
        ],
      ),
      body: Column(
        children: [
          const NetworkBanner(),
          Expanded(
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: LiveMixTheme.panel,
                        borderRadius: BorderRadius.circular(22),
                        border: Border.all(
                          color: _onAir
                              ? LiveMixTheme.live.withOpacity(0.45)
                              : Colors.transparent,
                        ),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 5,
                                  ),
                                  decoration: BoxDecoration(
                                    color: _onAir
                                        ? LiveMixTheme.liveSoft
                                        : LiveMixTheme.goldSoft,
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    _onAir ? 'ON AIR' : 'STANDBY',
                                    style: TextStyle(
                                      color: _onAir
                                          ? LiveMixTheme.live
                                          : LiveMixTheme.gold,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      letterSpacing: 1,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 18),
                                Text(
                                  'DURATION',
                                  style: GoogleFonts.outfit(
                                    color: LiveMixTheme.mute,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 1.2,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                LiveDurationText(elapsed: _elapsed),
                                const SizedBox(height: 12),
                                Text(
                                  _status ?? 'Preparing microphone…',
                                  style: const TextStyle(
                                    color: LiveMixTheme.mute,
                                    height: 1.35,
                                  ),
                                ),
                                const SizedBox(height: 14),
                                Row(
                                  children: [
                                    Icon(Icons.sensors, size: 16, color: linkColor),
                                    const SizedBox(width: 6),
                                    Text(
                                      _publisher.iceState ??
                                          (_publisher.isPreviewing
                                              ? 'Mic preview'
                                              : 'Idle'),
                                      style: TextStyle(
                                        color: linkColor,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 18),
                          SizedBox(
                            width: 56,
                            child: SignalMeter(
                              level: _publisher.level,
                              peak: _publisher.peak,
                              height: 160,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Text(
                        _error!,
                        style: const TextStyle(color: LiveMixTheme.bad),
                      ),
                    ],
                    const Spacer(),
                    Text(
                      'Tip: speak normally and keep SIGNAL in green. Full mixer (playlist, cues) stays on the web Studio.',
                      style: GoogleFonts.outfit(
                        color: LiveMixTheme.mute.withOpacity(0.9),
                        fontSize: 13,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (org != null) ...[
                      OutlinedButton.icon(
                        onPressed: () => _shareChannel(org),
                        icon: const Icon(Icons.ios_share_rounded),
                        label: const Text('Share channel link'),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        org.publicChannelUrl,
                        style: const TextStyle(
                          color: LiveMixTheme.mute,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    if (!_onAir)
                      FilledButton.icon(
                        onPressed: _busy ? null : _goLive,
                        icon: const Icon(Icons.podcasts_rounded),
                        label: Text(_busy ? 'Connecting…' : 'Go live'),
                      )
                    else
                      FilledButton.icon(
                        style: FilledButton.styleFrom(
                          backgroundColor: LiveMixTheme.live,
                          foregroundColor: Colors.white,
                        ),
                        onPressed: _busy ? null : _end,
                        icon: const Icon(Icons.stop_rounded),
                        label: Text(_busy ? 'Ending…' : 'End stream'),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ],
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
