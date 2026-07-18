<?php

namespace App\Console\Commands;

use App\Models\Stream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class StreamStudioUrl extends Command
{
    protected $signature = 'stream:studio-url {stream : Stream UUID}';

    protected $description = 'Print a temporary signed studio URL for broadcasting';

    public function handle(): int
    {
        $stream = Stream::query()->where('uuid', $this->argument('stream'))->firstOrFail();

        $url = URL::temporarySignedRoute(
            'studio.stream',
            now()->addMonths(6),
            ['stream' => $stream]
        );

        $this->line($url);

        return self::SUCCESS;
    }
}
