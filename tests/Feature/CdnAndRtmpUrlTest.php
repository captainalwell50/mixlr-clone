<?php

namespace Tests\Feature;

use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CdnAndRtmpUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_hls_uses_cdn_base_when_configured(): void
    {
        config([
            'streaming.mediamtx.hls_public_base' => 'https://origin.example.org/hls',
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $stream = $this->makeStream();

        $this->assertSame(
            'https://cdn.example.org/hls/'.$stream->mediaPath().'/index.m3u8',
            $stream->hlsPlaylistUrl()
        );
    }

    public function test_rtmp_helpers_include_stream_key(): void
    {
        config(['streaming.mediamtx.rtmp_public_base' => 'rtmp://stream.example.org:1935']);
        $stream = $this->makeStream();

        $this->assertSame('rtmp://stream.example.org:1935/'.$stream->mediaPath(), $stream->rtmpUrl());
        $this->assertStringContainsString('pass='.$stream->stream_key, $stream->rtmpStreamKeyForObs());
    }

    private function makeStream(): Stream
    {
        $org = Organization::query()->create(['name' => 'O', 'slug' => 'o-'.uniqid()]);

        return Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'T',
            'status' => StreamStatus::Offline,
        ]);
    }
}
