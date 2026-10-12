@extends('layouts.admin')

@section('title', 'Affiliate settings audit')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Super admin</p>
            <h1 class="console-title mt-2">Audit log</h1>
            <p class="console-lead">Each saved change records the setting, the previous value, the new value, the platform admin, and the time. Commission rates shown here were not applied to any payment.</p>
        </div>
    </div>

    <div class="console-table mt-6">
        <table>
            <thead>
                <tr>
                    <th>Setting</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Admin</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($audits as $audit)
                    <tr>
                        <td>{{ $labels[$audit->setting_key] ?? $audit->setting_key }}</td>
                        <td class="max-w-xs break-words text-[var(--stage-muted)]">{{ \App\Services\AffiliateProgrammeSettings::displayValue($audit->setting_key, $audit->old_value) }}</td>
                        <td class="max-w-xs break-words text-[var(--stage-cream)]">{{ \App\Services\AffiliateProgrammeSettings::displayValue($audit->setting_key, $audit->new_value) }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $audit->actor?->name ?? 'Unknown' }}</td>
                        <td class="text-[var(--stage-muted)]">{{ \App\Support\LocalTime::format($audit->created_at, 'M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-[var(--stage-muted)]">No configuration changes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $audits->links() }}</div>
@endsection