<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', [
            'supportEmail' => config('app.support_email'),
            'updated' => '2026-08-15',
        ]);
    }

    public function terms(): View
    {
        return view('legal.terms', [
            'supportEmail' => config('app.support_email'),
            'updated' => '2026-08-14',
        ]);
    }

    public function support(): View
    {
        return view('legal.support', [
            'supportEmail' => config('app.support_email'),
        ]);
    }
}
