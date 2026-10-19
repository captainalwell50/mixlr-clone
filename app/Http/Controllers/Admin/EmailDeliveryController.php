<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $status = $request->input('status');
        $type = $request->input('type');
        $search = $request->input('q');

        $query = EmailDelivery::query()->with('user')->latest();

        if (filled($status)) {
            $query->where('status', $status);
        }

        if (filled($type)) {
            $query->where('type', $type);
        }

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('recipient', 'like', "%{$search}%")
                  ->orWhere('event_key', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $deliveries = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => EmailDelivery::query()->count(),
            'sent' => EmailDelivery::query()->where('status', EmailDelivery::STATUS_SENT)->count(),
            'failed' => EmailDelivery::query()->where('status', EmailDelivery::STATUS_FAILED)->count(),
            'bounced' => EmailDelivery::query()->where('status', EmailDelivery::STATUS_BOUNCED)->count(),
            'delivered' => EmailDelivery::query()->where('status', EmailDelivery::STATUS_DELIVERED)->count(),
        ];

        return view('admin.email-deliveries.index', [
            'deliveries' => $deliveries,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentType' => $type,
            'search' => $search,
        ]);
    }
}
