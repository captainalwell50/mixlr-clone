<?php

namespace App\Services;

use App\Contracts\AudioSegmentConcatenator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class FfmpegAudioConcatenator implements AudioSegmentConcatenator
{
    public function trim(string $inputPath, string $outputPath, float $startSeconds, float $endSeconds): bool
    {
        $ffmpeg = $this->binary();
        if ($ffmpeg === null || ! is_readable($inputPath) || $endSeconds <= $startSeconds) {
            if ($ffmpeg === null) {
                Log::error('ffmpeg not found; cannot trim podcast.');
            }

            return false;
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $this->forgetOutput($outputPath);

        $bitrate = $this->audioBitrateK().'k';
        $result = Process::timeout(1800)->run([
            $ffmpeg,
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-i', $inputPath,
            '-ss', $this->clock($startSeconds),
            '-to', $this->clock($endSeconds),
            '-vn',
            '-map', '0:a:0',
            '-af', 'aresample=async=1000:first_pts=0',
            '-c:a', 'aac',
            '-profile:a', 'aac_low',
            '-ac', '2',
            '-b:a', $bitrate,
            '-ar', '48000',
            '-movflags', '+faststart',
            $outputPath,
        ]);

        if ($result->successful() && $this->outputLooksValid($outputPath)) {
            return true;
        }

        Log::error('ffmpeg podcast trim failed', [
            'output' => $outputPath,
            'start' => $startSeconds,
            'end' => $endSeconds,
            'error' => trim($result->errorOutput() ?: $result->output()),
        ]);
        $this->forgetOutput($outputPath);

        return false;
    }

    public function concatenate(array $inputPaths, string $outputPath): bool
    {
        $ffmpeg = $this->binary();
        if ($ffmpeg === null || $inputPaths === []) {
            if ($ffmpeg === null) {
                Log::error('ffmpeg not found; cannot assemble podcasts. Install ffmpeg on the app host (not only in the MediaMTX container).');
            }

            return false;
        }

        foreach ($inputPaths as $path) {
            if (! is_readable($path)) {
                Log::warning('ffmpeg concat skipped missing input', ['path' => $path]);

                return false;
            }
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $this->forgetOutput($outputPath);

        $bitrate = $this->audioBitrateK().'k';

        if (count($inputPaths) === 1) {
            $command = [
                $ffmpeg,
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $inputPaths[0],
                '-vn',
                '-map', '0:a:0',
                '-af', 'aresample=async=1000:first_pts=0',
                '-c:a', 'aac',
                '-profile:a', 'aac_low',
                '-ac', '2',
                '-b:a', $bitrate,
                '-ar', '48000',
                '-movflags', '+faststart',
                $outputPath,
            ];
        } else {
            $command = [
                $ffmpeg,
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
            ];

            $filterPreamble = '';
            $concatInputs = '';
            foreach ($inputPaths as $index => $path) {
                $command[] = '-i';
                $command[] = $path;
                $filterPreamble .= sprintf(
                    '[%d:a:0]aformat=sample_rates=48000:channel_layouts=stereo,aresample=async=1000:first_pts=0,asetpts=PTS-STARTPTS[a%d];',
                    $index,
                    $index
                );
                $concatInputs .= sprintf('[a%d]', $index);
            }

            $filterComplex = sprintf(
                '%s%sconcat=n=%d:v=0:a=1[a_concat];[a_concat]aresample=async=1000:first_pts=0[a_out]',
                $filterPreamble,
                $concatInputs,
                count($inputPaths)
            );

            array_push(
                $command,
                '-filter_complex', $filterComplex,
                '-map', '[a_out]',
                '-vn',
                '-c:a', 'aac',
                '-profile:a', 'aac_low',
                '-ac', '2',
                '-b:a', $bitrate,
                '-ar', '48000',
                '-movflags', '+faststart',
                $outputPath,
            );
        }

        $result = Process::timeout(1800)->run($command);

        if ($result->successful() && $this->outputLooksValid($outputPath)) {
            return true;
        }

        Log::error('ffmpeg archive encode failed', [
            'output' => $outputPath,
            'inputs' => count($inputPaths),
            'bitrate' => $bitrate,
            'error' => trim($result->errorOutput() ?: $result->output()),
        ]);

        $this->forgetOutput($outputPath);

        return false;
    }

    private function clock(float $seconds): string
    {
        return number_format(max(0, $seconds), 3, '.', '');
    }

    private function audioBitrateK(): int
    {
        return max(32, min(192, (int) config('streaming.archive_audio_bitrate_k', 128)));
    }

    private function binary(): ?string
    {
        $configured = config('streaming.ffmpeg_path');
        if (is_string($configured) && $configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        $which = Process::run(['which', 'ffmpeg']);
        if ($which->successful()) {
            $path = trim($which->output());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function outputLooksValid(string $outputPath): bool
    {
        clearstatcache(true, $outputPath);
        $size = is_file($outputPath) ? (int) filesize($outputPath) : 0;
        if ($size <= 1024) {
            Log::error('Archive output failed validation: file size too small or missing', [
                'output' => $outputPath,
                'size' => $size,
            ]);

            return false;
        }

        $probe = $this->probeFormat($outputPath);
        if ($probe === null) {
            return true;
        }

        if ($probe['duration'] < 0.5) {
            Log::error('Archive output failed validation: duration too short', [
                'output' => $outputPath,
                'duration' => $probe['duration'],
            ]);

            return false;
        }

        if ($probe['codec_name'] !== '' && $probe['codec_name'] !== 'aac') {
            Log::error('Archive output failed validation: unexpected audio codec', [
                'output' => $outputPath,
                'codec_name' => $probe['codec_name'],
            ]);

            return false;
        }

        if ($probe['sample_rate'] > 0 && $probe['sample_rate'] !== 48000) {
            Log::error('Archive output failed validation: unexpected sample rate', [
                'output' => $outputPath,
                'sample_rate' => $probe['sample_rate'],
            ]);

            return false;
        }

        if ($probe['channels'] > 0 && $probe['channels'] !== 2) {
            Log::error('Archive output failed validation: unexpected channel count', [
                'output' => $outputPath,
                'channels' => $probe['channels'],
            ]);

            return false;
        }

        $bitrate = $probe['bitrate'] > 0
            ? $probe['bitrate']
            : (int) (($size * 8) / $probe['duration']);

        $floor = (int) ($this->audioBitrateK() * 1000 * 0.25);
        if ($bitrate < $floor) {
            Log::error('Archive output failed validation: bitrate below floor', [
                'output' => $outputPath,
                'bitrate' => $bitrate,
                'floor' => $floor,
            ]);

            return false;
        }

        if ($this->hasPathologicalPacketDuration($outputPath)) {
            Log::error('Archive output failed validation: pathological packet durations detected', [
                'output' => $outputPath,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Inspect audio packet durations.
     * Standard AAC at 48 kHz produces frames of 1024 samples (~0.021333s).
     * Any non-terminal packet with duration > 0.15s indicates timestamp corruption.
     */
    private function hasPathologicalPacketDuration(string $outputPath): bool
    {
        $ffprobe = $this->ffprobeBinary();
        if ($ffprobe === null) {
            return false;
        }

        $cmd = escapeshellcmd($ffprobe)
            .' -v error -select_streams a:0 -show_entries packet=duration_time -of default=noprint_wrappers=1:nokey=1 '
            .escapeshellarg($outputPath);

        $pipe = @popen($cmd, 'r');
        if (! is_resource($pipe)) {
            return false;
        }

        $previousDuration = null;
        $hasPathological = false;

        while (($line = fgets($pipe)) !== false) {
            if ($previousDuration !== null && $previousDuration > 0.15) {
                $hasPathological = true;
                break;
            }

            $trimmed = trim($line);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                $previousDuration = (float) $trimmed;
            }
        }

        pclose($pipe);

        return $hasPathological;
    }

    /**
     * @return array{duration: float, bitrate: int, codec_name: string, sample_rate: int, channels: int}|null Null when ffprobe cannot be used.
     */
    private function probeFormat(string $outputPath): ?array
    {
        $ffprobe = $this->ffprobeBinary();
        if ($ffprobe === null) {
            return null;
        }

        $result = Process::timeout(30)->run([
            $ffprobe,
            '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name,sample_rate,channels',
            '-show_entries', 'format=duration,bit_rate',
            '-of', 'default=nw=1',
            $outputPath,
        ]);

        if (! $result->successful()) {
            return [
                'duration' => 0.0,
                'bitrate' => 0,
                'codec_name' => '',
                'sample_rate' => 0,
                'channels' => 0,
            ];
        }

        $duration = 0.0;
        $bitrate = 0;
        $codecName = '';
        $sampleRate = 0;
        $channels = 0;

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            if (str_starts_with($line, 'duration=') && is_numeric(substr($line, 9))) {
                $duration = (float) substr($line, 9);
            }
            if (str_starts_with($line, 'bit_rate=') && is_numeric(substr($line, 9))) {
                $bitrate = (int) substr($line, 9);
            }
            if (str_starts_with($line, 'codec_name=')) {
                $codecName = trim(substr($line, 11));
            }
            if (str_starts_with($line, 'sample_rate=') && is_numeric(substr($line, 12))) {
                $sampleRate = (int) substr($line, 12);
            }
            if (str_starts_with($line, 'channels=') && is_numeric(substr($line, 9))) {
                $channels = (int) substr($line, 9);
            }
        }

        return [
            'duration' => $duration,
            'bitrate' => $bitrate,
            'codec_name' => $codecName,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
        ];
    }

    private function ffprobeBinary(): ?string
    {
        $ffmpeg = $this->binary();
        if ($ffmpeg !== null) {
            $beside = dirname($ffmpeg).'/ffprobe';
            if (is_executable($beside)) {
                return $beside;
            }
        }

        $which = Process::run(['which', 'ffprobe']);
        if ($which->successful()) {
            $path = trim($which->output());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', '/opt/homebrew/bin/ffprobe'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function forgetOutput(string $outputPath): void
    {
        if (is_file($outputPath)) {
            @unlink($outputPath);
        }
    }
}
