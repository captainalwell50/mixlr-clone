<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * MediaMTX authMethod: http callback.
 *
 * @see https://mediamtx.org/docs/features/authentication
 */
class MediaMtxAuthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $action = (string) $request->input('action', '');
        $path = (string) $request->input('path', '');
        $parsed = $this->parseLivePath($path);

        if ($parsed === null) {
            return response('forbidden', Response::HTTP_FORBIDDEN);
        }

        $stream = Stream::query()->where('uuid', $parsed['uuid'])->first();

        if ($stream === null) {
            return response('unknown stream', Response::HTTP_FORBIDDEN);
        }

        return match ($action) {
            'read', 'playback' => response('ok', Response::HTTP_OK),
            'publish' => $this->authorizePublish($request, $stream, $parsed['aac']),
            default => response('forbidden', Response::HTTP_FORBIDDEN),
        };
    }

    private function authorizePublish(Request $request, Stream $stream, bool $aacSidecar): Response
    {
        // Internal ffmpeg Opus→AAC remux publishes to live/<uuid>/aac from loopback.
        if ($aacSidecar && $this->isLoopbackIp((string) $request->input('ip', ''))) {
            return response('ok', Response::HTTP_OK);
        }

        $candidates = $this->credentialCandidates($request);
        $global = config('streaming.mediamtx.publish_secret');
        $streamKey = (string) $stream->stream_key;

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($streamKey !== '' && hash_equals($streamKey, $candidate)) {
                return response('ok', Response::HTTP_OK);
            }
            if (is_string($global) && $global !== '' && hash_equals($global, $candidate)) {
                return response('ok', Response::HTTP_OK);
            }
        }

        // No secrets configured at all → allow (dev convenience)
        if (($streamKey === '' || $streamKey === null)
            && (! is_string($global) || $global === '')) {
            return response('ok', Response::HTTP_OK);
        }

        // RTSP clients often probe without credentials first — ask for them.
        if ($candidates === [] || $this->allEmpty($candidates)) {
            return response('credentials required', Response::HTTP_UNAUTHORIZED);
        }

        return response('publish secret required', Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array{uuid: string, aac: bool}|null
     */
    private function parseLivePath(string $path): ?array
    {
        if (! preg_match(
            '/^live\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(\/aac)?$/i',
            $path,
            $matches
        )) {
            return null;
        }

        return [
            'uuid' => strtolower($matches[1]),
            'aac' => ($matches[2] ?? '') === '/aac',
        ];
    }

    private function isLoopbackIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return str_starts_with($ip, '127.');
    }

    /**
     * @param  list<string>  $candidates
     */
    private function allEmpty(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function credentialCandidates(Request $request): array
    {
        $candidates = [
            (string) $request->input('password', ''),
            (string) $request->input('token', ''),
            (string) $request->input('user', ''),
        ];

        $query = (string) $request->input('query', '');
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['publish_secret', 'pass', 'password', 'stream_key'] as $key) {
                if (isset($params[$key]) && is_string($params[$key])) {
                    $candidates[] = $params[$key];
                }
            }
        }

        return $candidates;
    }
}
