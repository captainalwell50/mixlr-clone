<?php

namespace App\Http\Controllers;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscoverController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $live = Event::query()
            ->with('organization')
            ->where('status', EventStatus::Live)
            ->where('access', EventAccess::Public)
            ->whereHas('organization', fn ($org) => $org->where('is_public', true))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->latest('started_at')
            ->limit(48)
            ->get();

        $upcoming = Event::query()
            ->with('organization')
            ->where('status', EventStatus::Scheduled)
            ->where('access', EventAccess::Public)
            ->whereHas('organization', fn ($org) => $org->where('is_public', true))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', '%'.$q.'%')
                        ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->orderBy('scheduled_at')
            ->limit(24)
            ->get();

        return view('discover', compact('live', 'upcoming', 'q'));
    }
}
