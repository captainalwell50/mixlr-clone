<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(): View
    {
        $recordings = Recording::query()
            ->with('stream.organization')
            ->latest('completed_at')
            ->paginate(10);

        return view('archive', compact('recordings'));
    }
}
