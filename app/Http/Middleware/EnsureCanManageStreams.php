<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageStreams
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $stream = $request->route('stream');
        if ($stream !== null && $user->canManageStream($stream)) {
            return $next($request);
        }

        $event = $request->route('event');
        if ($event instanceof Event && $user->canManageEvent($event)) {
            return $next($request);
        }

        if ($stream === null && $event === null && $user->manageableOrganizations()->exists()) {
            return $next($request);
        }

        abort(403);
    }
}
