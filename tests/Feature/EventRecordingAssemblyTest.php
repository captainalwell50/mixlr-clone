<?php

namespace Tests\Feature;

use App\Contracts\AudioSegmentConcatenator;
use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Jobs\AssembleEventRecordingJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Recording;
use App\Models\Stream;
use App\Services\EventBroadcastService;
use App\Services\EventRecordingAssemblyService;
use App\Services\FfmpegAudioConcatenator;
use App\Services\RecordingStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventRecordingAssemblyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('mediamtx_recordings');
        $this->fakeAudioConcatenator();
    }

    public function test_concatenates_reconnect_parts_into_one_public_file_and_deletes_parts(): void
    {
        [$event, $stream] = $this->endedEvent();
        $this->putPart($event, $stream, 'part-a.mp4', 500_000, '60', now()->subMinutes(2));
        $this->putPart($event, $stream, 'part-b.mp4', 500_000, '90', now()->subMinute());

        $assembled = app(EventRecordingAssemblyService::class)->finalizeEvent($event);

        $this->assertNotNull($assembled);
        $this->assertSame(Recording::SOURCE_ASSEMBLED, $assembled->source);
        $this->assertFalse($assembled->is_public);
        $this->assertSame(1_000_000, $assembled->size_bytes);
        $this->assertSame('150', $assembled->duration_raw);
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($assembled->relative_path));

        $this->assertSame(0, Recording::query()->where('source', Recording::SOURCE_MEDIAMTX)->count());
        $this->assertSame(0, Recording::query()->where('is_public', true)->count());
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/part-a.mp4'));
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/part-b.mp4'));
    }

    public function test_discards_thin_junk_chunks_instead_of_publishing_them(): void
    {
        [$event, $stream] = $this->endedEvent();
        $good = $this->putPart($event, $stream, 'good.mp4', 800_000, '60', now()->subMinutes(2));
        $this->putPart($event, $stream, 'junk.mp4', 120_000, '1685', now()->subMinute());

        $assembled = app(EventRecordingAssemblyService::class)->finalizeEvent($event);

        $this->assertNotNull($assembled);
        $this->assertSame($good->id, $assembled->id);
        $this->assertSame(Recording::SOURCE_ASSEMBLED, $assembled->source);
        $this->assertFalse($assembled->is_public);
        $this->assertNull(Recording::query()->where('relative_path', $stream->mediaPath().'/junk.mp4')->first());
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/junk.mp4'));
    }

    public function test_studio_upload_wins_and_auto_files_are_deleted(): void
    {
        [$event, $stream] = $this->endedEvent();
        $this->putPart($event, $stream, 'auto.mp4', 500_000, '60');

        Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => $event->title,
            'source' => Recording::SOURCE_STUDIO,
            'is_public' => true,
            'relative_path' => $stream->mediaPath().'/studio.webm',
            'size_bytes' => 400_000,
            'duration_raw' => '80',
            'completed_at' => now(),
        ]);
        Storage::disk('mediamtx_recordings')->put($stream->mediaPath().'/studio.webm', str_repeat('s', 400_000));

        $result = app(EventRecordingAssemblyService::class)->finalizeEvent($event);

        $this->assertSame(Recording::SOURCE_STUDIO, $result?->source);
        $this->assertSame(0, Recording::query()->whereIn('source', [
            Recording::SOURCE_MEDIAMTX,
            Recording::SOURCE_ASSEMBLED,
        ])->count());
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/auto.mp4'));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/studio.webm'));
    }

    public function test_missing_studio_file_assembles_leftover_untracked_parts_and_keeps_play_url(): void
    {
        [$event, $stream] = $this->endedEvent();
        $studio = Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => $event->title,
            'source' => Recording::SOURCE_STUDIO,
            'is_public' => true,
            'relative_path' => $stream->mediaPath().'/studio.webm',
            'size_bytes' => 400_000,
            'duration_raw' => '80',
            'completed_at' => now(),
        ]);

        $event->forceFill([
            'started_at' => '2026-09-10 17:40:00',
            'ended_at' => '2026-09-10 18:40:00',
        ])->save();

        $orphan = $stream->mediaPath().'/2026-09-10_17-43-50-352422.mp4';
        Storage::disk('mediamtx_recordings')->put($orphan, str_repeat('a', 500_000));
        $orphanB = $stream->mediaPath().'/2026-09-10_17-49-57-468879.mp4';
        Storage::disk('mediamtx_recordings')->put($orphanB, str_repeat('b', 500_000));
        $settled = now()->subMinutes(10)->getTimestamp();
        touch(Storage::disk('mediamtx_recordings')->path($orphan), $settled);
        touch(Storage::disk('mediamtx_recordings')->path($orphanB), $settled);

        $result = app(EventRecordingAssemblyService::class)->finalizeEvent($event->fresh());

        $this->assertNotNull($result);
        $this->assertSame($studio->id, $result->id);
        $this->assertSame(Recording::SOURCE_ASSEMBLED, $result->source);
        $this->assertTrue($result->is_public);
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($result->relative_path));
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($orphan));
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($orphanB));
        $this->assertSame(1, Recording::query()->where('is_public', true)->count());
    }

    public function test_end_live_assembles_instead_of_publishing_every_chunk(): void
    {
        [$event, $stream] = $this->endedEvent(EventStatus::Live);
        $this->putPart($event, $stream, 'a.mp4', 500_000, '60', now()->subMinutes(3));
        $this->putPart($event, $stream, 'b.mp4', 600_000, '70', now()->subMinutes(1));

        app(EventBroadcastService::class)->markEnded($event);

        $this->assertSame(EventStatus::Ended, $event->fresh()->status);
        $this->assertSame(0, Recording::query()->where('is_public', true)->count());
        $this->assertFalse(Recording::query()->where('source', Recording::SOURCE_ASSEMBLED)->first()?->is_public);
        $this->assertSame(0, Recording::query()->where('source', Recording::SOURCE_MEDIAMTX)->count());
        $assembled = Recording::query()->where('source', Recording::SOURCE_ASSEMBLED)->first();
        $this->assertNotNull($assembled);
        $this->assertSame(1_100_000, $assembled->size_bytes);
    }

    public function test_does_not_assemble_while_event_is_still_live(): void
    {
        [$event, $stream] = $this->endedEvent(EventStatus::Live);
        $this->putPart($event, $stream, 'live.mp4', 500_000, '60');

        $this->assertNull(app(EventRecordingAssemblyService::class)->finalizeEvent($event));
        $this->assertSame(1, Recording::query()->where('source', Recording::SOURCE_MEDIAMTX)->where('is_public', false)->count());
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/live.mp4'));
    }

    public function test_assemble_job_is_quiet_when_ended_event_has_no_files_yet(): void
    {
        [$event] = $this->endedEvent();

        AssembleEventRecordingJob::dispatch($event->id);

        $this->assertSame(0, Recording::query()->count());
    }

    public function test_prunes_old_untracked_reconnect_files_but_keeps_catalogued_and_recent(): void
    {
        config(['streaming.mediamtx.untracked_grace_minutes' => 20]);

        [$event, $stream] = $this->endedEvent();
        $kept = $this->putPart($event, $stream, 'kept.mp4', 500_000, '60');

        $orphan = $stream->mediaPath().'/orphan.mp4';
        Storage::disk('mediamtx_recordings')->put($orphan, str_repeat('x', 80_000));
        touch(
            Storage::disk('mediamtx_recordings')->path($orphan),
            now()->subHours(2)->getTimestamp(),
        );

        $recent = $stream->mediaPath().'/recording-now.mp4';
        Storage::disk('mediamtx_recordings')->put($recent, str_repeat('y', 90_000));

        $result = app(EventRecordingAssemblyService::class)->pruneUntrackedFiles();

        $this->assertSame(1, $result['deleted']);
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($orphan));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($kept->relative_path));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($recent));
    }

    public function test_does_not_adopt_a_later_broadcast_into_an_older_event(): void
    {
        [$event, $stream] = $this->endedEvent();
        $event->forceFill([
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subMonths(2)->addHour(),
        ])->save();

        $podcast = $this->putPart($event, $stream, 'event_old.mp4', 800_000, '60', $event->ended_at);
        $podcast->forceFill([
            'source' => Recording::SOURCE_ASSEMBLED,
            'is_public' => true,
        ])->save();

        $live = $stream->mediaPath().'/'.now()->utc()->format('Y-m-d_H-i-s').'-111111.mp4';
        Storage::disk('mediamtx_recordings')->put($live, str_repeat('a', 900_000));

        $result = app(EventRecordingAssemblyService::class)->finalizeEvent($event->fresh());

        $this->assertSame($podcast->id, $result?->id);
        $this->assertSame($stream->mediaPath().'/event_old.mp4', $result->relative_path);
        $this->assertTrue($result->completed_at->equalTo($event->ended_at));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($live));
        $this->assertNull(Recording::query()->where('relative_path', $live)->first());
    }

    public function test_does_not_adopt_a_segment_still_being_written(): void
    {
        [$event, $stream] = $this->endedEvent();
        $event->forceFill([
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(5),
        ])->save();

        $name = now()->subMinutes(30)->utc()->format('Y-m-d_H-i-s').'-100000.mp4';
        $relative = $stream->mediaPath().'/'.$name;
        Storage::disk('mediamtx_recordings')->put($relative, str_repeat('a', 500_000));

        app(EventRecordingAssemblyService::class)->finalizeEvent($event->fresh());

        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($relative));
        $this->assertNull(Recording::query()->where('relative_path', $relative)->first());
    }

    public function test_compresses_oversized_public_recording_to_archive_bitrate(): void
    {
        $this->fakeAudioConcatenator(shrink: true);

        [$event, $stream] = $this->endedEvent();
        $fat = $this->putPart($event, $stream, 'fat.mp4', 2_000_000, '30');
        $fat->forceFill([
            'source' => Recording::SOURCE_ASSEMBLED,
            'is_public' => true,
        ])->save();

        $updated = app(EventRecordingAssemblyService::class)->compressIfOversized($fat->fresh());

        $this->assertSame($fat->id, $updated->id);
        $this->assertSame(20_000, $updated->size_bytes);
        $this->assertFalse(Storage::disk('mediamtx_recordings')->exists($stream->mediaPath().'/fat.mp4'));
        $this->assertTrue(Storage::disk('mediamtx_recordings')->exists($updated->relative_path));
    }

    public function test_reassembles_existing_aac_assembly_plus_new_opus_segment_into_valid_archive(): void
    {
        config(['streaming.mediamtx.assemble_min_usable_bytes' => 1000]);

        // Unbind the FakeAudioConcatenator to test the real production assembly pipeline
        $this->app->forgetInstance(AudioSegmentConcatenator::class);
        $this->app->singleton(AudioSegmentConcatenator::class, FfmpegAudioConcatenator::class);

        [$event, $stream] = $this->endedEvent();

        $storage = app(RecordingStorageService::class);
        $disk = $storage->localDisk();

        // 1. Existing assembled AAC recording
        $aacRel = $stream->mediaPath().'/event_'.$event->id.'_prev_assembled.mp4';
        $aacAbs = $disk->path($aacRel);
        @mkdir(dirname($aacAbs), 0775, true);
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2.0',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', '-ac', '2',
            $aacAbs,
        ]);

        $existingAssembled = Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => $event->title,
            'source' => Recording::SOURCE_ASSEMBLED,
            'is_public' => false,
            'relative_path' => $aacRel,
            'size_bytes' => filesize($aacAbs),
            'duration_raw' => '2.0',
            'completed_at' => now()->subMinutes(5),
        ]);

        // 2. New raw Opus fMP4 segment (e.g. from reconnect)
        $opusRel = $stream->mediaPath().'/part_reconnect_opus.mp4';
        $opusAbs = $disk->path($opusRel);
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=880:duration=1.5',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $opusAbs,
        ]);

        $newOpus = Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => $event->title,
            'source' => Recording::SOURCE_MEDIAMTX,
            'is_public' => false,
            'relative_path' => $opusRel,
            'size_bytes' => filesize($opusAbs),
            'duration_raw' => '1.5',
            'completed_at' => now()->subMinute(),
        ]);

        // Run reassembly through EventRecordingAssemblyService
        $result = app(EventRecordingAssemblyService::class)->finalizeEvent($event);

        $this->assertNotNull($result);
        $this->assertSame(Recording::SOURCE_ASSEMBLED, $result->source);
        $this->assertTrue($disk->exists($result->relative_path));

        $finalAbs = $disk->path($result->relative_path);

        // Verify with ffprobe that the re-assembled archive is valid AAC 48k stereo
        $probe = Process::run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name,sample_rate,channels',
            '-show_entries', 'format=duration,bit_rate',
            '-of', 'default=nw=1',
            $finalAbs,
        ]);

        $this->assertTrue($probe->successful());
        $output = $probe->output();
        $this->assertStringContainsString('codec_name=aac', $output);
        $this->assertStringContainsString('sample_rate=48000', $output);
        $this->assertStringContainsString('channels=2', $output);

        // Verify packet duration cadence has no pathological spikes (>0.15s)
        $packetProbe = Process::run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'packet=duration_time',
            '-of', 'csv=p=0',
            $finalAbs,
        ]);
        $packets = array_filter(explode("\n", trim($packetProbe->output())));
        $this->assertNotEmpty($packets);
        foreach (array_slice($packets, 0, -1) as $p) {
            $this->assertLessThan(0.15, (float) trim($p));
        }

        // Clean up disk files
        @unlink($finalAbs);
        @unlink($aacAbs);
        @unlink($opusAbs);
    }

    /**
     * @return array{0: Event, 1: Stream}
     */
    private function endedEvent(EventStatus $status = EventStatus::Ended): array
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Anniversary Day 3',
            'status' => $status,
            'ended_at' => $status === EventStatus::Ended ? now() : null,
        ]);

        return [$event, $stream];
    }

    private function putPart(
        Event $event,
        Stream $stream,
        string $filename,
        int $bytes,
        string $duration,
        ?\DateTimeInterface $completedAt = null,
        bool $public = false,
    ): Recording {
        $relative = $stream->mediaPath().'/'.$filename;
        Storage::disk('mediamtx_recordings')->put($relative, str_repeat('a', $bytes));

        return Recording::query()->create([
            'stream_id' => $stream->id,
            'event_id' => $event->id,
            'title' => $event->title,
            'source' => Recording::SOURCE_MEDIAMTX,
            'is_public' => $public,
            'relative_path' => $relative,
            'size_bytes' => $bytes,
            'duration_raw' => $duration,
            'completed_at' => $completedAt ?? now(),
        ]);
    }
}
