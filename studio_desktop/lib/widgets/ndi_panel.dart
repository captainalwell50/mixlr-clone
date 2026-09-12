import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models.dart';
import '../services/api_client.dart';
import '../services/live_board_sync.dart';
import '../services/ndi_bridge.dart';
import '../theme.dart';

const _ndiToolsUrl = 'https://ndi.video/tools/';
final _ndiToolsUri = Uri.parse(_ndiToolsUrl);

/// Church Live Board: receive EasyWorship / vMix / OBS over NDI and cue listen.
class NdiPanel extends StatefulWidget {
  const NdiPanel({
    super.key,
    required this.api,
    required this.streamUuid,
    this.liveBoard,
  });

  final ApiClient api;
  final String streamUuid;
  final LiveBoardSync? liveBoard;

  @override
  State<NdiPanel> createState() => _NdiPanelState();
}

class _NdiPanelState extends State<NdiPanel> {
  final NdiBridge _ndi = NdiBridge();
  bool _busy = false;
  bool _showOnListen = true;
  bool _uploading = false;
  DateTime? _lastUpload;
  String _status = 'Looking for NDI sources…';

  @override
  void initState() {
    super.initState();
    widget.liveBoard?.addListener(_onLiveBoard);
    _ndi.onFrame = _onFrame;
    _ndi.addListener(_onNdi);
    unawaited(_boot());
  }

  @override
  void dispose() {
    widget.liveBoard?.removeListener(_onLiveBoard);
    _ndi.onFrame = null;
    _ndi.removeListener(_onNdi);
    _ndi.dispose();
    super.dispose();
  }

  void _onLiveBoard() {
    if (!mounted) return;
    final mode = widget.liveBoard?.mode;
    if (mode == LiveBoardMode.scripture || mode == LiveBoardMode.song) {
      setState(() => _status = 'No NDI on listen');
    }
  }

  void _onNdi() {
    if (mounted) setState(() {});
  }

  Future<void> _boot() async {
    await _ndi.refreshStatus();
    await _ndi.listSources();
    if (!mounted) return;
    setState(() {
      _status = _ndi.message.isNotEmpty
          ? _ndi.message
          : (_ndi.sources.isEmpty
              ? 'No NDI sources yet — enable NDI in EasyWorship, vMix, or OBS.'
              : 'Pick a source to show on listen.');
    });
  }

  Future<void> _onFrame(NdiFrame frame) async {
    if (!_showOnListen || _uploading) return;
    final last = _lastUpload;
    if (last != null && DateTime.now().difference(last) < const Duration(milliseconds: 450)) {
      return;
    }
    _uploading = true;
    _lastUpload = DateTime.now();
    try {
      await widget.api.ndiCue(
        widget.streamUuid,
        jpegBytes: frame.jpeg,
        source: frame.source,
      );
      widget.liveBoard?.markNdi();
      if (mounted) {
        setState(() => _status = 'Showing ${frame.source} on listen');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not send NDI to listen.');
    } finally {
      _uploading = false;
    }
  }

  Future<void> _connect(NdiSource source) async {
    setState(() {
      _busy = true;
      _status = 'Connecting to ${source.name}…';
    });
    try {
      await _ndi.connect(source.name);
      if (mounted) {
        setState(() => _status = _showOnListen
            ? 'Receiving ${source.name} — showing on listen'
            : 'Receiving ${source.name} — preview only');
      }
    } on PlatformException catch (e) {
      if (mounted) setState(() => _status = e.message ?? 'Could not connect.');
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not connect to ${source.name}.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openNdiTools() async {
    await launchUrl(_ndiToolsUri, mode: LaunchMode.externalApplication);
  }

  Future<void> _clear() async {
    try {
      await widget.api.ndiClear(widget.streamUuid);
      widget.liveBoard?.markCleared();
      if (mounted) setState(() => _status = 'NDI cleared from listen');
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not clear NDI.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final frame = _ndi.lastFrame;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      decoration: BoxDecoration(
        color: StudioTheme.panel,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StudioTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'NDI / Stage',
            style: GoogleFonts.outfit(
              color: StudioTheme.cream,
              fontWeight: FontWeight.w700,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Pull EasyWorship, vMix, or OBS slides onto listen automatically.',
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12, height: 1.35),
          ),
          const SizedBox(height: 10),
          if (frame != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: Image.memory(
                  frame.jpeg,
                  fit: BoxFit.contain,
                  gaplessPlayback: true,
                ),
              ),
            )
          else
            Container(
              height: 96,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: StudioTheme.ink,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                _ndi.available ? 'Waiting for an NDI frame' : 'NDI runtime not installed',
                style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
              ),
            ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton(
                onPressed: _busy ? null : () => unawaited(_boot()),
                child: const Text('Refresh sources'),
              ),
              OutlinedButton(
                onPressed: _ndi.connected ? () => unawaited(_ndi.disconnect()) : null,
                child: const Text('Disconnect'),
              ),
              OutlinedButton(
                onPressed: () => unawaited(_clear()),
                child: const Text('Clear listen'),
              ),
            ],
          ),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: Text(
              'Show on listen',
              style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 13),
            ),
            value: _showOnListen,
            onChanged: (value) => setState(() => _showOnListen = value),
          ),
          if (_ndi.sources.isNotEmpty)
            ..._ndi.sources.map(
              (source) => ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(
                  source.name,
                  style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 13),
                ),
                trailing: Text(
                  _ndi.source == source.name && _ndi.connected ? 'LIVE' : 'Connect',
                  style: GoogleFonts.outfit(
                    color: _ndi.source == source.name ? StudioTheme.live : StudioTheme.accentBright,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                onTap: _busy ? null : () => unawaited(_connect(source)),
              ),
            ),
          const SizedBox(height: 4),
          if (!_ndi.available) ...[
            Text(
              'Install NDI Tools, then restart Studio.',
              style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
            ),
            const SizedBox(height: 4),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  foregroundColor: StudioTheme.accentBright,
                ),
                onPressed: () => unawaited(_openNdiTools()),
                child: Text(
                  _ndiToolsUrl,
                  style: GoogleFonts.outfit(
                    color: StudioTheme.accentBright,
                    fontSize: 12,
                    decoration: TextDecoration.underline,
                    decorationColor: StudioTheme.accentBright,
                  ),
                ),
              ),
            ),
          ] else
            Text(
              _status,
              style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
            ),
        ],
      ),
    );
  }
}
