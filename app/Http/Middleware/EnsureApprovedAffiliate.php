<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->affiliateAccount;

        if ($account === null || ! $account->canRefer()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
