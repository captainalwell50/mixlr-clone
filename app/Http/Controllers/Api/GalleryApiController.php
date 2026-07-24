<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GalleryController;
use App\Models\GalleryImage;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryApiController extends Controller
{
    public function __construct(private GalleryController $gallery) {}

    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->gallery->index($request, $stream);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->gallery->store($request, $stream);
    }

    public function destroy(Request $request, Stream $stream, GalleryImage $image): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->gallery->destroy($request, $stream, $image);
    }

    public function background(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->gallery->storeBackground($request, $stream);
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
