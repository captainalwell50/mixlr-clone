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

        if (! $this->isLiveStreamPath($path)) {
            return response('forbidden', Response::HTTP_FORBIDDEN);
        }

        $uuid = substr($path, strlen('live/'));
        $stream = Stream::query()->where('uuid', $uuid)->first();

        if ($stream === null) {
            return response('unknown stream', Response::HTTP_FORBIDDEN);
        }

        return match ($action) {
            'read', 'playback' => response('ok', Response::HTTP_OK),
            'publish' => $this->authorizePublish($request, $stream),
            default => response('forbidden', Response::HTTP_FORBIDDEN),
        };
    }

    private function authorizePublish(Request $request, Stream $stream): Response
    {
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

        return response('publish secret required', Response::HTTP_FORBIDDEN);
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

    private function isLiveStreamPath(string $path): bool
    {
        return (bool) preg_match(
            '/^live\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $path
        );
    }
}
