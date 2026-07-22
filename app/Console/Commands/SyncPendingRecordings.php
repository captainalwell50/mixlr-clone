<?php

namespace App\Console\Commands;

use App\Jobs\SyncRecordingToObjectStorage;
use App\Models\Recording;
use App\Services\RecordingStorageService;
use Illuminate\Console\Command;

class SyncPendingRecordings extends Command
{
    protected $signature = 'recordings:sync-pending {--sync : Run uploads inline instead of queueing}';

    protected $description = 'Upload local recordings that are not yet on object storage';

    public function handle(RecordingStorageService $storage): int
    {
        if (! $storage->objectStorageEnabled()) {
            $this->warn('Object storage is disabled (OBJECT_STORAGE_ENABLED=false).');

            return self::SUCCESS;
        }

        $query = Recording::query()
            ->whereNull('synced_at')
            ->orderBy('id');

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$count} recording(s)…");

        $query->chunkById(50, function ($recordings) use ($storage): void {
            foreach ($recordings as $recording) {
                if ($this->option('sync')) {
                    $ok = $storage->syncToObjectStorage($recording);
                    $this->line(($ok ? 'OK' : 'FAIL').' #'.$recording->id);
                } else {
                    SyncRecordingToObjectStorage::dispatch($recording->id);
                    $this->line('Queued #'.$recording->id);
                }
            }
        });

        return self::SUCCESS;
    }
}
