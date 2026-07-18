<?php

namespace App\Http\Controllers;

use App\Models\Stream;

class StudioController extends Controller
{
    public function show(Stream $stream)
    {
        return view('studio', [
            'stream' => $stream,
            'whipUrl' => $stream->whipUrl(),
            'organization' => $stream->organization,
        ]);
    }
}
