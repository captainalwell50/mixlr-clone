import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// Church-only song / announcement cue panel (EasyWorship-style slides).
class SongPanel extends StatefulWidget {
  const SongPanel({
    super.key,
    required this.api,
    required this.streamUuid,
  });

  final ApiClient api;
  final String streamUuid;

  @override
  State<SongPanel> createState() => _SongPanelState();
}

class _SongPanelState extends State<SongPanel> {
  final _title = TextEditingController();
  final _body = TextEditingController();
  List<DisplaySongItem> _songs = const [];
  SongCue? _cue;
  String _status = 'No song on listen';
  bool _busy = false;
  bool _formOpen = false;
  int? _editingId;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    try {
      final result = await widget.api.songsIndex(widget.streamUuid);
      if (!mounted) return;
      setState(() {
        _songs = result.songs;
        _cue = result.cue;
        if (_cue != null) {
          _status =
              'Showing “${_cue!.title}” · slide ${_cue!.slideIndex + 1}/${_cue!.slideCount}';
        } else if (_status.startsWith('Showing')) {
          _status = 'No song on listen';
        }
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not load songs.');
    }
  }

  void _openForm([DisplaySongItem? song]) {
    setState(() {
      _formOpen = true;
      _editingId = song?.id;
      _title.text = song?.title ?? '';
      _body.text = (song?.slides ?? const []).join('\n\n');
    });
  }

  void _closeForm() {
    setState(() {
      _formOpen = false;
      _editingId = null;
      _title.clear();
      _body.clear();
    });
  }

  Future<void> _save() async {
    final title = _title.text.trim();
    final body = _body.text.trim();
    if (title.isEmpty || body.isEmpty) {
      setState(() => _status = 'Title and slides are required.');
      return;
    }
    setState(() => _busy = true);
    try {
      if (_editingId != null) {
        await widget.api.songUpdate(
          widget.streamUuid,
          _editingId!,
          title: title,
          body: body,
        );
        _status = 'Song updated';
      } else {
        await widget.api.songStore(
          widget.streamUuid,
          title: title,
          body: body,
        );
        _status = 'Song saved';
      }
      _closeForm();
      await _refresh();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } catch (_) {
      if (mounted) setState(() => _status = 'Could not save song.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cue(DisplaySongItem song) async {
    setState(() => _busy = true);
    try {
      final cue = await widget.api.songCue(widget.streamUuid, song.id);
      if (!mounted) return;
      setState(() {
        _cue = cue;
        _status =
            'Showing “${cue.title}” · slide ${cue.slideIndex + 1}/${cue.slideCount}';
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _clear() async {
    setState(() => _busy = true);
    try {
      await widget.api.songClear(widget.streamUuid);
      if (!mounted) return;
      setState(() {
        _cue = null;
        _status = 'Song cleared from listen';
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _nudge(bool next) async {
    setState(() => _busy = true);
    try {
      final cue = next
          ? await widget.api.songNext(widget.streamUuid)
          : await widget.api.songPrevious(widget.streamUuid);
      if (!mounted) return;
      setState(() {
        _cue = cue;
        _status =
            'Showing “${cue.title}” · slide ${cue.slideIndex + 1}/${cue.slideCount}';
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete(DisplaySongItem song) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete song?'),
        content: Text('Delete “${song.title}”?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      await widget.api.songDelete(widget.streamUuid, song.id);
      if (!mounted) return;
      setState(() => _status = 'Song deleted');
      await _refresh();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: StudioTheme.panelHi.withOpacity(0.9),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StudioTheme.accent.withOpacity(0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'SONG',
            style: GoogleFonts.outfit(
              color: StudioTheme.mute,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton(
                onPressed: _busy ? null : () => _openForm(),
                style: FilledButton.styleFrom(backgroundColor: StudioTheme.accent),
                child: const Text('New'),
              ),
              OutlinedButton(
                onPressed: _busy || _cue == null ? null : () => _nudge(false),
                child: const Text('Prev'),
              ),
              OutlinedButton(
                onPressed: _busy || _cue == null ? null : () => _nudge(true),
                child: const Text('Next'),
              ),
              OutlinedButton(
                onPressed: _busy || _cue == null ? null : _clear,
                child: const Text('Clear'),
              ),
            ],
          ),
          if (_cue != null) ...[
            const SizedBox(height: 8),
            Text(
              'Live: ${_cue!.title} · ${_cue!.slideIndex + 1}/${_cue!.slideCount}',
              style: GoogleFonts.outfit(color: StudioTheme.accentBright, fontSize: 12),
            ),
          ],
          if (_formOpen) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _title,
              style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 14),
              decoration: _fieldDecoration('Title'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _body,
              minLines: 4,
              maxLines: 8,
              style: GoogleFonts.outfit(color: StudioTheme.cream, fontSize: 14),
              decoration: _fieldDecoration('Slides (blank line between)'),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                FilledButton(
                  onPressed: _busy ? null : _save,
                  style: FilledButton.styleFrom(backgroundColor: StudioTheme.accent),
                  child: const Text('Save'),
                ),
                OutlinedButton(
                  onPressed: _busy ? null : _closeForm,
                  child: const Text('Cancel'),
                ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          if (_songs.isEmpty)
            Text(
              'No songs yet',
              style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
            )
          else
            ..._songs.map((song) {
              final live = _cue?.id == song.id;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: StudioTheme.ink.withOpacity(0.55),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: live
                          ? StudioTheme.accent.withOpacity(0.45)
                          : StudioTheme.line,
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        song.title,
                        style: GoogleFonts.outfit(
                          color: StudioTheme.cream,
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      Text(
                        '${song.slideCount} slide${song.slideCount == 1 ? '' : 's'}',
                        style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          FilledButton(
                            onPressed: _busy ? null : () => _cue(song),
                            style: FilledButton.styleFrom(
                              backgroundColor: StudioTheme.accent,
                              visualDensity: VisualDensity.compact,
                            ),
                            child: const Text('Go live'),
                          ),
                          OutlinedButton(
                            onPressed: _busy ? null : () => _openForm(song),
                            style: OutlinedButton.styleFrom(
                              visualDensity: VisualDensity.compact,
                            ),
                            child: const Text('Edit'),
                          ),
                          OutlinedButton(
                            onPressed: _busy ? null : () => _delete(song),
                            style: OutlinedButton.styleFrom(
                              visualDensity: VisualDensity.compact,
                            ),
                            child: const Text('Delete'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
          const SizedBox(height: 4),
          Text(
            _status,
            style: GoogleFonts.outfit(color: StudioTheme.mute, fontSize: 12),
          ),
        ],
      ),
    );
  }

  InputDecoration _fieldDecoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: GoogleFonts.outfit(color: StudioTheme.mute),
      filled: true,
      fillColor: StudioTheme.ink.withOpacity(0.55),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: StudioTheme.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: StudioTheme.line),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    );
  }
}
