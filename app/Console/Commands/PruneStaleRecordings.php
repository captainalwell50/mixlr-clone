<?php

namespace App\Console\Commands;

use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Console\Command;

class PruneStaleRecordings extends Command
{
    protected $signature = 'recordings:prune {--dry-run : List rows that would be deleted without deleting}';

    protected $description = 'Delete recording files and DB rows older than RECORDING_RETENTION_DAYS';

    public function handle(RecordingStorageService $storage): int
    {
        $days = max(1, (int) config('streaming.mediamtx.recording_retention_days', 365));
        $cutoff = now()->subDays($days);

        $query = Recording::query()
            ->where('completed_at', '<', $cutoff)
            ->orderBy('id');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No stale recordings.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$count} recording(s) completed before ".$cutoff->toIso8601String());

            return self::SUCCESS;
        }

        $query->chunk(100, function ($recordings) use ($storage): void {
            foreach ($recordings as $recording) {
                $storage->deleteFiles($recording);
                $recording->delete();
            }
        });

        $this->info("Deleted {$count} stale recording(s).");

        return self::SUCCESS;
    }
}
