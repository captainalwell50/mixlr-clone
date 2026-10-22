<?php

namespace Tests\Unit;

use App\Services\FfmpegAudioConcatenator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FfmpegAudioConcatenatorTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    private function tempPath(string $suffix = '.mp4'): string
    {
        $path = sys_get_temp_dir().'/sml-test-'.uniqid().$suffix;
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_returns_false_and_logs_when_ffmpeg_is_missing(): void
    {
        config(['streaming.ffmpeg_path' => '/tmp/soundmix-ffmpeg-does-not-exist']);

        Log::spy();

        $input = $this->tempPath();
        $output = $this->tempPath();
        file_put_contents($input, str_repeat('a', 2048));

        $ok = app(FfmpegAudioConcatenator::class)->concatenate([$input], $output);

        $this->assertFalse($ok);
        Log::shouldHaveReceived('error')->withArgs(function (string $message): bool {
            return str_contains($message, 'ffmpeg not found');
        })->once();
    }

    public function test_fixture_a_clean_opus_fmp4(): void
    {
        $input = $this->tempPath();
        $output = $this->tempPath();

        // Fixture A: clean Opus stream in fragmented MP4 (MediaMTX recorder output)
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $input,
        ]);
        $this->assertFileExists($input);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->concatenate([$input], $output);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        $this->assertEqualsWithDelta(2.0, $probe['duration'], 0.15);
        $this->assertGreaterThan(50000, $probe['bit_rate']);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        $this->assertEqualsWithDelta(0.0, (float) $packets[0]['pts_time'], 0.05);

        foreach (array_slice($packets, 0, -1) as $packet) {
            $this->assertEqualsWithDelta(0.021333, (float) $packet['duration_time'], 0.005);
        }
    }

    public function test_fixture_b_opus_fmp4_with_timestamp_gaps(): void
    {
        $input = $this->tempPath();
        $output = $this->tempPath();

        // Fixture B: Opus stream in fMP4 with dropped packets / 3-second timestamp jump
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
            '-af', 'asetpts=if(gt(N\,25)\,PTS+3/TB\,PTS)',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $input,
        ]);
        $this->assertFileExists($input);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->concatenate([$input], $output);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        // aresample inserts silence over the 3-second gap, total synchronized duration is ~5.0s
        $this->assertEqualsWithDelta(5.0, $probe['duration'], 0.2);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        $this->assertEqualsWithDelta(0.0, (float) $packets[0]['pts_time'], 0.05);

        // All non-terminal packets must have normalized AAC duration (~0.021333s), no pathological spikes
        foreach (array_slice($packets, 0, -1) as $packet) {
            $this->assertLessThan(0.15, (float) $packet['duration_time']);
            $this->assertEqualsWithDelta(0.021333, (float) $packet['duration_time'], 0.005);
        }
    }

    public function test_fixture_c_multiple_opus_segments(): void
    {
        $part1 = $this->tempPath();
        $part2 = $this->tempPath();
        $output = $this->tempPath();

        // Fixture C: multiple short Opus segments to be concatenated
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1.0',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $part1,
        ]);
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=880:duration=1.5',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $part2,
        ]);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->concatenate([$part1, $part2], $output);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        $this->assertEqualsWithDelta(2.5, $probe['duration'], 0.15);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        foreach (array_slice($packets, 0, -1) as $packet) {
            $this->assertLessThan(0.15, (float) $packet['duration_time']);
            $this->assertEqualsWithDelta(0.021333, (float) $packet['duration_time'], 0.005);
        }
    }

    public function test_fixture_d_existing_aac_mp4_concatenated_with_opus_fmp4(): void
    {
        $aacPart = $this->tempPath();
        $opusPart = $this->tempPath();
        $output = $this->tempPath();

        // Fixture D: existing assembled AAC MP4 + new Opus fMP4 fragment (event re-assembly)
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1.5',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '48000',
            $aacPart,
        ]);
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=880:duration=1.0',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $opusPart,
        ]);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->concatenate([$aacPart, $opusPart], $output);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        $this->assertEqualsWithDelta(2.5, $probe['duration'], 0.15);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        foreach (array_slice($packets, 0, -1) as $packet) {
            $this->assertLessThan(0.15, (float) $packet['duration_time']);
            $this->assertEqualsWithDelta(0.021333, (float) $packet['duration_time'], 0.005);
        }
    }

    public function test_fixture_e_assembled_aac_concatenated_with_gapped_offset_opus_fmp4(): void
    {
        $fragA = $this->tempPath();
        $fragB = $this->tempPath();
        $output = $this->tempPath();

        // Frag A: assembled AAC (2.0s, 48000 Hz stereo)
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2.0',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', '-ac', '2',
            $fragA,
        ]);

        // Frag B: Opus/fMP4 with non-zero start PTS (+10s) and an internal timestamp discontinuity (3s jump)
        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=880:duration=2.0',
            '-af', 'asetpts=if(gt(N\,25)\,PTS+13/TB\,PTS+10/TB)',
            '-c:a', 'libopus',
            '-movflags', 'empty_moov+default_base_moof',
            $fragB,
        ]);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->concatenate([$fragA, $fragB], $output);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        // 2.0s frag A + 2.0s frag B audio + 3.0s padded gap = ~7.0s
        $this->assertEqualsWithDelta(7.0, $probe['duration'], 0.2);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        $this->assertEqualsWithDelta(0.0, (float) $packets[0]['pts_time'], 0.05);

        // Monotonic cadence, no packet duration > 0.15s, no failure at concat boundary
        $prevPts = -1.0;
        foreach (array_slice($packets, 0, -1) as $packet) {
            $pts = (float) $packet['pts_time'];
            $dur = (float) $packet['duration_time'];

            $this->assertGreaterThan($prevPts, $pts, 'Timeline must be strictly monotonic');
            $prevPts = $pts;

            $this->assertLessThan(0.15, $dur, 'Packet duration must not be pathological');
            $this->assertEqualsWithDelta(0.021333, $dur, 0.005);
        }
    }

    public function test_trim_normalizes_timestamps_and_starts_at_zero(): void
    {
        $input = $this->tempPath();
        $output = $this->tempPath();

        Process::run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=4.0',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '48000',
            $input,
        ]);

        $concatenator = app(FfmpegAudioConcatenator::class);
        $ok = $concatenator->trim($input, $output, 1.0, 3.0);

        $this->assertTrue($ok);
        $this->assertFileExists($output);

        $probe = $this->probeAudio($output);
        $this->assertSame('aac', $probe['codec_name']);
        $this->assertSame(48000, $probe['sample_rate']);
        $this->assertSame(2, $probe['channels']);
        $this->assertEqualsWithDelta(2.0, $probe['duration'], 0.15);

        $packets = $this->probePackets($output);
        $this->assertNotEmpty($packets);
        $this->assertEqualsWithDelta(0.0, (float) $packets[0]['pts_time'], 0.05);
        foreach (array_slice($packets, 0, -1) as $packet) {
            $this->assertLessThan(0.15, (float) $packet['duration_time']);
            $this->assertEqualsWithDelta(0.021333, (float) $packet['duration_time'], 0.005);
        }
    }

    private function probeAudio(string $path): array
    {
        $result = Process::run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name,sample_rate,channels',
            '-show_entries', 'format=duration,bit_rate',
            '-of', 'default=nw=1',
            $path,
        ]);

        $out = [
            'codec_name' => '',
            'sample_rate' => 0,
            'channels' => 0,
            'duration' => 0.0,
            'bit_rate' => 0,
        ];

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            if (str_starts_with($line, 'codec_name=')) {
                $out['codec_name'] = trim(substr($line, 11));
            }
            if (str_starts_with($line, 'sample_rate=')) {
                $out['sample_rate'] = (int) substr($line, 12);
            }
            if (str_starts_with($line, 'channels=')) {
                $out['channels'] = (int) substr($line, 9);
            }
            if (str_starts_with($line, 'duration=')) {
                $out['duration'] = (float) substr($line, 9);
            }
            if (str_starts_with($line, 'bit_rate=')) {
                $out['bit_rate'] = (int) substr($line, 9);
            }
        }

        return $out;
    }

    /**
     * @return list<array{pts_time: string, duration_time: string}>
     */
    private function probePackets(string $path): array
    {
        $result = Process::run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'packet=pts_time,duration_time',
            '-of', 'csv=p=0',
            $path,
        ]);

        $packets = [];
        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $packets[] = [
                    'pts_time' => trim($parts[0]),
                    'duration_time' => trim($parts[1]),
                ];
            }
        }

        return $packets;
    }
}
