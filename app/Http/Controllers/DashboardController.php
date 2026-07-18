<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->user()?->is_admin) {
            return redirect()->route('admin.streams.index');
        }

        return view('dashboard');
    }
}
