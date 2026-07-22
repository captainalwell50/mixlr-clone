<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRecordingToObjectStorage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $recordingId) {}

    public function handle(RecordingStorageService $storage): void
    {
        $recording = Recording::query()->with('stream')->find($this->recordingId);
        if ($recording === null) {
            return;
        }

        if ($recording->synced_at && filled($recording->object_key)) {
            return;
        }

        if (! $storage->objectStorageEnabled()) {
            return;
        }

        $ok = $storage->syncToObjectStorage($recording);
        if (! $ok) {
            Log::warning('Recording object storage sync failed', [
                'recording_id' => $this->recordingId,
                'path' => $recording->relative_path,
            ]);
            throw new \RuntimeException('Recording sync failed for #'.$this->recordingId);
        }
    }
}
