import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models.dart';
import '../services/api_client.dart';
import '../services/live_board_sync.dart';
import '../theme.dart';

/// Church-only song / announcement cue panel (EasyWorship-style slides).
class SongPanel extends StatefulWidget {
  const SongPanel({
    super.key,
    required this.api,
    required this.streamUuid,
    this.liveBoard,
  });

  final ApiClient api;
  final String streamUuid;
  final LiveBoardSync? liveBoard;

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
    widget.liveBoard?.addListener(_onLiveBoard);
    _body.addListener(_onBodyChanged);
    _refresh();
  }

  void _onLiveBoard() {
    if (!mounted) return;
    if (widget.liveBoard?.mode == LiveBoardMode.scripture) {
      setState(() {
        _cue = null;
        _status = 'No song on listen';
      });
    }
  }

  @override
  void dispose() {
    widget.liveBoard?.removeListener(_onLiveBoard);
    _body.removeListener(_onBodyChanged);
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  void _onBodyChanged() {
    if (_formOpen && mounted) setState(() {});
  }

  List<String> _slidesFromBody(String body) {
    return body
        .split(RegExp(r'\n\s*\n'))
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();
  }

  List<String> _slidesFor(DisplaySongItem song) {
    if (song.slides.isNotEmpty) return song.slides;
    if (_cue?.id == song.id && (_cue?.text.isNotEmpty ?? false)) {
      return [_cue!.text];
    }
    return const [];
  }

  SongCue? _localCueFrom(DisplaySongItem song, int index) {
    final slides = _slidesFor(song);
    if (slides.isEmpty) return null;
    final i = index.clamp(0, slides.length - 1);
    return SongCue(
      id: song.id,
      title: song.title,
      text: slides[i],
      slideIndex: i,
      slideCount: slides.length,
    );
  }

  DisplaySongItem? _songById(int? id) {
    if (id == null) return null;
    for (final song in _songs) {
      if (song.id == id) return song;
    }
    return null;
  }

  Future<void> _refresh() async {
    try {
      final result = await widget.api.songsIndex(widget.streamUuid);
      if (!mounted) return;
      final mode = liveBoardModeFrom(result.liveBoard);
      setState(() {
        _songs = result.songs;
        _cue = mode == LiveBoardMode.scripture ? null : result.cue;
        if (_cue != null) {
          _status =
              'Showing “${_cue!.title}” · slide ${_cue!.slideIndex + 1}/${_cue!.slideCount}';
        } else if (_status.startsWith('Showing')) {
          _status = 'No song on listen';
        }
      });
      if (mode != null) widget.liveBoard?.apply(mode);
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

  Future<void> _cueSong(DisplaySongItem song, {int slideIndex = 0}) async {
    final local = _localCueFrom(song, slideIndex);
    setState(() {
      _busy = true;
      if (local != null) {
        _cue = local;
        _status =
            'Showing “${local.title}” · slide ${local.slideIndex + 1}/${local.slideCount}';
      }
    });
    try {
      final cue = await widget.api.songCue(
        widget.streamUuid,
        song.id,
        slideIndex: slideIndex,
      );
      if (!mounted) return;
      setState(() {
        _cue = cue;
        _status =
            'Showing “${cue.title}” · slide ${cue.slideIndex + 1}/${cue.slideCount}';
      });
      widget.liveBoard?.markSong();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
      await _refresh();
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
      widget.liveBoard?.markCleared();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _nudge(bool next) async {
    final current = _cue;
    final song = _songById(current?.id);
    setState(() {
      _busy = true;
      if (song != null && current != null) {
        final local = _localCueFrom(song, current.slideIndex + (next ? 1 : -1));
        if (local != null) {
          _cue = local;
          _status =
              'Showing “${local.title}” · slide ${local.slideIndex + 1}/${local.slideCount}';
        }
      }
    });
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
      widget.liveBoard?.markSong();
    } on ApiException catch (e) {
      if (mounted) setState(() => _status = e.message);
      await _refresh();
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
            const SizedBox(height: 10),
            _livePreview(_cue!),
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
            if (_slidesFromBody(_body.text).isNotEmpty) ...[
              const SizedBox(height: 8),
              _slideList(
                slides: _slidesFromBody(_body.text),
                songId: _editingId,
                interactive: false,
              ),
            ],
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
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 4,
                        runSpacing: 2,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          FilledButton(
                            onPressed: _busy ? null : () => _cueSong(song),
                            style: FilledButton.styleFrom(
                              backgroundColor: StudioTheme.accent,
                              foregroundColor: StudioTheme.ink,
                              visualDensity: VisualDensity.compact,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              minimumSize: Size.zero,
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              textStyle: GoogleFonts.outfit(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(6),
                              ),
                            ),
                            child: const Text('Go live'),
                          ),
                          TextButton(
                            onPressed: _busy ? null : () => _openForm(song),
                            style: TextButton.styleFrom(
                              foregroundColor: StudioTheme.mute,
                              visualDensity: VisualDensity.compact,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              minimumSize: Size.zero,
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                              textStyle: GoogleFonts.outfit(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            child: const Text('Edit'),
                          ),
                          TextButton(
                            onPressed: _busy ? null : () => _delete(song),
                            style: TextButton.styleFrom(
                              foregroundColor: StudioTheme.live.withOpacity(0.62),
                              visualDensity: VisualDensity.compact,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              minimumSize: Size.zero,
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                              textStyle: GoogleFonts.outfit(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            child: const Text('Delete'),
                          ),
                        ],
                      ),
                      if (_slidesFor(song).isNotEmpty) ...[
                        const SizedBox(height: 8),
                        _slideList(
                          slides: _slidesFor(song),
                          songId: song.id,
                          interactive: !_busy,
                          onSelect: (index) => _cueSong(song, slideIndex: index),
                        ),
                      ],
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

  Widget _livePreview(SongCue cue) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
      decoration: BoxDecoration(
        color: StudioTheme.ink.withOpacity(0.72),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: StudioTheme.accent.withOpacity(0.28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'LIVE: ${cue.title} · SLIDE ${cue.slideIndex + 1}/${cue.slideCount}',
            style: GoogleFonts.outfit(
              color: StudioTheme.accentBright,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.4,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            cue.text,
            style: GoogleFonts.outfit(
              color: StudioTheme.cream,
              fontSize: 14,
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }

  Widget _slideList({
    required List<String> slides,
    int? songId,
    bool interactive = true,
    ValueChanged<int>? onSelect,
  }) {
    final active = _cue?.id == songId ? _cue!.slideIndex : -1;
    return ConstrainedBox(
      constraints: const BoxConstraints(maxHeight: 220),
      child: ListView.separated(
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        itemCount: slides.length,
        separatorBuilder: (_, __) => const SizedBox(height: 4),
        itemBuilder: (context, i) {
          final selected = i == active;
          return Material(
            color: selected
                ? StudioTheme.accent.withOpacity(0.16)
                : StudioTheme.ink.withOpacity(0.35),
            borderRadius: BorderRadius.circular(8),
            child: InkWell(
              onTap: interactive && onSelect != null ? () => onSelect(i) : null,
              borderRadius: BorderRadius.circular(8),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: selected
                        ? StudioTheme.accent.withOpacity(0.55)
                        : Colors.transparent,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 18,
                      child: Text(
                        '${i + 1}',
                        style: GoogleFonts.outfit(
                          color: StudioTheme.mute,
                          fontSize: 11,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        slides[i],
                        maxLines: selected ? 8 : 2,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.outfit(
                          color: StudioTheme.cream,
                          fontSize: 12,
                          height: 1.35,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
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
