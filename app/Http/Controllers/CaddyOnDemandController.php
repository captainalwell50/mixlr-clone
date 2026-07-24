<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Caddy on-demand TLS ask endpoint.
 * Returns 200 only when the requested host is a real channel subdomain.
 */
class CaddyOnDemandController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $domain = strtolower(trim((string) $request->query('domain', '')));
        $base = strtolower((string) config('app.channel_domain'));

        if ($domain === '' || $base === '' || ! str_ends_with($domain, '.'.$base)) {
            return response('denied', 404);
        }

        $slug = substr($domain, 0, - (strlen($base) + 1));
        if ($slug === '' || str_contains($slug, '.')) {
            return response('denied', 404);
        }

        $reserved = array_map('strtolower', config('app.channel_reserved_subdomains', []));
        if (in_array($slug, $reserved, true)) {
            return response('denied', 404);
        }

        // Issue certs for any real channel host; page visibility stays in ChannelController.
        $exists = Organization::query()->where('slug', $slug)->exists();

        return $exists
            ? response('ok', 200)
            : response('denied', 404);
    }
}
