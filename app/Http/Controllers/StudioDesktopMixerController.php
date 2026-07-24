<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudioDesktopMixerController extends Controller
{
    public function show(Request $request, Stream $stream): View
    {
        $this->authorizeAccess($request, $stream);

        return view('studio-desktop-mixer', [
            'stream' => $stream,
        ]);
    }

    private function authorizeAccess(Request $request, Stream $stream): void
    {
        $user = $request->user();
        $canManage = $user?->canManageOrganization($stream->organization)
            || $user?->canManageStream($stream);
        $signed = $request->hasValidSignature();

        abort_unless($canManage || $signed, 403);
    }
}
