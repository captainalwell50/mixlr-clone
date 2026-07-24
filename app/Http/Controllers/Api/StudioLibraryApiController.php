<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudioAudioLibraryController;
use App\Models\Stream;
use App\Models\StudioAudioAsset;
use App\Services\GoogleDriveService;
use App\Services\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioLibraryApiController extends Controller
{
    public function __construct(private StudioAudioLibraryController $library) {}

    public function mixerEmbed(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return response()->json([
            'embed_url' => URL::temporarySignedRoute(
                'studio.desktop-mixer',
                now()->addHours(12),
                ['stream' => $stream],
            ),
            'whip_url' => $stream->whipUrl(),
            'stream' => [
                'uuid' => $stream->uuid,
                'title' => $stream->title,
            ],
        ]);
    }

    public function index(Request $request, Stream $stream, StorageQuotaService $quota): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->library->index($request, $stream, $quota);
    }

    public function store(
        Request $request,
        Stream $stream,
        StorageQuotaService $quota,
        GoogleDriveService $drive,
    ): JsonResponse {
        $this->authorizeManage($request, $stream);

        return $this->library->store($request, $stream, $quota, $drive);
    }

    public function destroy(Request $request, Stream $stream, StudioAudioAsset $asset): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->library->destroy($request, $stream, $asset);
    }

    public function file(
        Request $request,
        Stream $stream,
        StudioAudioAsset $asset,
        GoogleDriveService $drive,
    ): StreamedResponse|Response {
        $this->authorizeManage($request, $stream);

        return $this->library->file($request, $stream, $asset, $drive);
    }

    private function authorizeManage(Request $request, Stream $stream): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(
            $user->canManageOrganization($stream->organization) || $user->canManageStream($stream),
            403,
        );
    }
}
