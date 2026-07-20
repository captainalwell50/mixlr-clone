<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (auth()->user()?->is_admin) {
            return redirect()->route('admin.streams.index');
        }

        if (! auth()->user()?->organizations()->exists()) {
            return redirect()->route('onboarding.show');
        }

        return redirect()->route('creator.home');
    }
}
