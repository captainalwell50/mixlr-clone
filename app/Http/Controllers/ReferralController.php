<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AffiliateAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ReferralController extends Controller
{
    public function show(Request $request, string $code, AffiliateAttributionService $attribution): RedirectResponse
    {
        $attribution->captureVisit($request, $code);

        return redirect()->to($this->destinationFor($request->user()));
    }

    private function destinationFor(?User $user): string
    {
        if ($user === null) {
            if (config('app.registration_enabled') && Route::has('register')) {
                return route('register');
            }

            return route('login');
        }

        if ($user->isAdmin()) {
            return route('admin.streams.index');
        }

        if ($user->organizations()->exists()) {
            return route('creator.home');
        }

        return route('onboarding.show');
    }
}
