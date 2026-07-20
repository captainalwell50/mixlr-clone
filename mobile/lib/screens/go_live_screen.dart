import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../services/auth_state.dart';
import '../services/whip_publisher.dart';
import '../theme.dart';

class GoLiveScreen extends StatefulWidget {
  const GoLiveScreen({super.key, required this.stream});

  final StreamSummary stream;

  @override
  State<GoLiveScreen> createState() => _GoLiveScreenState();
}

class _GoLiveScreenState extends State<GoLiveScreen> {
  final _publisher = WhipPublisher();
  bool _busy = false;
  bool _onAir = false;
  String? _status;
  String? _error;

  @override
  void dispose() {
    _publisher.stop();
    super.dispose();
  }

  Future<void> _goLive() async {
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

      setState(() => _status = 'Connecting microphone…');
      await _publisher.start(publish.whipUrl);

      setState(() => _status = 'Marking stream live…');
      await api.goLive(widget.stream.uuid);

      setState(() {
        _onAir = true;
        _status = 'On air — listeners can join now.';
      });
    } on ApiException catch (e) {
      await _publisher.stop();
      setState(() => _error = e.message);
    } catch (e) {
      await _publisher.stop();
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
      if (!mounted) return;
      setState(() {
        _onAir = false;
        _status = 'Offline';
      });
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
    return Scaffold(
      appBar: AppBar(title: Text(widget.stream.title)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                height: 180,
                decoration: BoxDecoration(
                  color: LiveMixTheme.panel,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Center(
                  child: Icon(
                    _onAir ? Icons.mic : Icons.mic_none,
                    size: 72,
                    color: _onAir ? const Color(0xFFFF6B6B) : LiveMixTheme.gold,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                _onAir ? 'YOU ARE LIVE' : 'Ready to publish',
                style: TextStyle(
                  color: _onAir ? const Color(0xFFFF6B6B) : LiveMixTheme.mist,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _status ??
                    'This uses your phone mic and sends audio to MediaMTX over WHIP. Full studio mixer stays on the web.',
                style: const TextStyle(color: LiveMixTheme.mute, height: 1.4),
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                Text(_error!, style: const TextStyle(color: Colors.redAccent)),
              ],
              const Spacer(),
              if (!_onAir)
                FilledButton(
                  onPressed: _busy ? null : _goLive,
                  child: Text(_busy ? 'Connecting…' : 'Go live'),
                )
              else
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFE24B4B),
                    foregroundColor: Colors.white,
                  ),
                  onPressed: _busy ? null : _end,
                  child: Text(_busy ? 'Ending…' : 'End stream'),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
