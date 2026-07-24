<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ScriptureController;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScriptureApiController extends Controller
{
    public function __construct(private ScriptureController $scripture) {}

    public function show(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->scripture->show($stream);
    }

    public function suggest(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->scripture->suggest($request, $stream);
    }

    public function store(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->scripture->store($request, $stream);
    }

    public function destroy(Request $request, Stream $stream): JsonResponse
    {
        $this->authorizeManage($request, $stream);

        return $this->scripture->destroy($request, $stream);
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
