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
            'streaming.listen.hls_aac_sidecar' => false,
        ]);

        $stream = $this->makeStream();

        $this->assertSame(
            'https://cdn.example.org/hls/'.$stream->mediaPath().'/index.m3u8',
            $stream->hlsPlaylistUrl()
        );
    }

    public function test_hls_uses_aac_sidecar_path_when_enabled(): void
    {
        config([
            'streaming.mediamtx.hls_public_base' => 'https://origin.example.org/hls',
            'streaming.mediamtx.hls_cdn_base' => null,
            'streaming.listen.hls_aac_sidecar' => true,
        ]);

        $stream = $this->makeStream();

        $this->assertSame(
            'https://origin.example.org/hls/'.$stream->mediaPath().'/aac/index.m3u8',
            $stream->hlsPlaylistUrl()
        );
    }

    public function test_prefer_hls_auto_when_cdn_configured(): void
    {
        config([
            'streaming.listen.prefer_hls' => null,
            'streaming.listen.hls_aac_sidecar' => false,
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $stream = $this->makeStream();

        $this->assertTrue($stream->preferHlsListen());
        $this->assertSame('hls', $stream->playbackMode());
    }

    public function test_prefer_hls_auto_when_aac_sidecar_enabled(): void
    {
        config([
            'streaming.listen.prefer_hls' => null,
            'streaming.listen.hls_aac_sidecar' => true,
            'streaming.mediamtx.hls_cdn_base' => null,
        ]);

        $stream = $this->makeStream();

        $this->assertTrue($stream->preferHlsListen());
        $this->assertSame('hls', $stream->playbackMode());
    }

    public function test_prefer_hls_explicit_false_wins(): void
    {
        config([
            'streaming.listen.prefer_hls' => false,
            'streaming.listen.hls_aac_sidecar' => true,
            'streaming.mediamtx.hls_cdn_base' => 'https://cdn.example.org/hls',
        ]);

        $stream = $this->makeStream();

        $this->assertFalse($stream->preferHlsListen());
        $this->assertSame('whep', $stream->playbackMode());
    }

    public function test_default_prefers_whep_without_cdn_or_sidecar(): void
    {
        config([
            'streaming.listen.prefer_hls' => null,
            'streaming.listen.hls_aac_sidecar' => false,
            'streaming.mediamtx.hls_cdn_base' => null,
        ]);

        $stream = $this->makeStream();

        $this->assertFalse($stream->preferHlsListen());
        $this->assertSame('whep', $stream->playbackMode());
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
