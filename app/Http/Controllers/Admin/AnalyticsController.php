<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\EventHeart;
use App\Models\ListenerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = now()->subDays($days);

        $eventQuery = Event::query()->with('organization');
        if (! $user->isAdmin()) {
            $orgIds = $user->manageableOrganizations()->pluck('organizations.id');
            $eventQuery->whereIn('organization_id', $orgIds);
        }

        $events = (clone $eventQuery)
            ->where(function ($q) use ($since) {
                $q->where('started_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->latest('started_at')
            ->limit(40)
            ->get();

        $eventIds = $events->pluck('id');

        $uniqueListeners = (int) ListenerSession::query()
            ->whereIn('event_id', $eventIds)
            ->where('started_at', '>=', $since)
            ->selectRaw('COUNT(DISTINCT session_key) as aggregate')
            ->value('aggregate');

        $hearts = EventHeart::query()
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $since)
            ->count();

        $chats = ChatMessage::query()
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $since)
            ->count();

        $perEvent = ListenerSession::query()
            ->select('event_id', DB::raw('COUNT(DISTINCT session_key) as unique_listeners'))
            ->whereIn('event_id', $eventIds)
            ->groupBy('event_id')
            ->pluck('unique_listeners', 'event_id');

        return view('admin.analytics.index', compact(
            'events',
            'days',
            'uniqueListeners',
            'hearts',
            'chats',
            'perEvent',
        ));
    }
}
