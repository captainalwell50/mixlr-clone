<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SongController;
use App\Models\DisplaySong;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongApiController extends Controller
{
    public function __construct(private SongController $songs) {}

    public function show(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->show($stream);
    }

    public function index(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->index($request, $stream);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->store($request, $stream);
    }

    public function update(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->update($request, $stream, $song);
    }

    public function destroy(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->destroy($request, $stream, $song);
    }

    public function cue(Request $request, Stream $stream, DisplaySong $song): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->cue($request, $stream, $song);
    }

    public function next(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->next($request, $stream);
    }

    public function previous(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->previous($request, $stream);
    }

    public function clear(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->songs->clear($request, $stream);
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
