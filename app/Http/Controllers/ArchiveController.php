<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(): View
    {
        $counts = Recording::query()
            ->where('recordings.is_public', true)
            ->join('streams', 'streams.id', '=', 'recordings.stream_id')
            ->join('organizations', 'organizations.id', '=', 'streams.organization_id')
            ->where('organizations.is_public', true)
            ->groupBy('organizations.id')
            ->select('organizations.id', DB::raw('count(*) as podcasts_count'))
            ->pluck('podcasts_count', 'id');

        $channels = Organization::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get()
            ->map(function (Organization $org) use ($counts) {
                $org->setAttribute('podcasts_count', (int) $counts->get($org->id, 0));

                return $org;
            });

        return view('archive', compact('channels'));
    }

    public function channel(Request $request, Organization $organization): View
    {
        $canManage = (bool) $request->user()?->canManageOrganization($organization);
        abort_unless($organization->is_public || $canManage, 404);

        $recordings = Recording::query()
            ->whereHas('stream', fn ($q) => $q->where('organization_id', $organization->id))
            ->where(function ($query) use ($canManage) {
                $query->where('recordings.is_public', true);
                if ($canManage) {
                    $query->orWhere(function ($drafts) {
                        $drafts->where('recordings.is_public', false)
                            ->whereIn('recordings.source', [
                                Recording::SOURCE_ASSEMBLED,
                                Recording::SOURCE_STUDIO,
                            ]);
                    });
                }
            })
            ->with(['stream', 'event'])
            ->orderedForArchive()
            ->paginate(12);

        return view('archive-channel', compact('organization', 'recordings', 'canManage'));
    }
}
