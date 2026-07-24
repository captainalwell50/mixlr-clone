<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChannelSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('organization');
        $slug = strtolower($param instanceof Organization
            ? (string) $param->slug
            : (string) $param);
        $reserved = array_map('strtolower', config('app.channel_reserved_subdomains', []));

        if ($slug === '' || in_array($slug, $reserved, true)) {
            abort(404);
        }

        return $next($request);
    }
}
