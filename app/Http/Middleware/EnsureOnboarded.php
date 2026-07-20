<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return $next($request);
        }

        if ($user->organizations()->exists()) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('onboarding.show');
    }
}
