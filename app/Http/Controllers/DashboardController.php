<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        if ($user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        if ($user?->organizations()->exists()) {
            return redirect()->route('creator.home');
        }

        if (session('onboarding.creator_type') || session('intent') === 'creator') {
            return redirect()->route('onboarding.show');
        }

        return redirect()->route('discover');
    }
}
