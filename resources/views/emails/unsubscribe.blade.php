@extends('layouts.app')

@section('title', 'Email Preferences')

@section('content')
    <div class="mx-auto max-w-md">
        <p class="site-section-label">Sound Mix Live</p>
        <h1 class="console-title mt-2">Email Preferences</h1>
        <p class="console-lead">Manage communication preferences for <strong>{{ $user->email }}</strong>.</p>

        @if (session('status'))
            <div class="mt-4 rounded-md bg-emerald-500/10 p-4 text-sm text-emerald-400 border border-emerald-500/20">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-8 rounded-lg bg-[var(--stage-card)] p-6 border border-[var(--stage-border)] space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Transactional & Account Notifications</h2>
                <p class="text-sm text-[var(--stage-muted)] mt-1">
                    Receipts, invoices, security alerts, and broadcast event notices are essential to your service and cannot be turned off.
                </p>
            </div>

            <hr class="border-[var(--stage-border)]">

            <div>
                <h2 class="text-base font-semibold text-white">Marketing & Product Updates</h2>
                <p class="text-sm text-[var(--stage-muted)] mt-1">
                    Occasional updates regarding new SoundMix studio features, hardware integrations, and special offers.
                </p>
                <div class="mt-4">
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('email.unsubscribe.update', $user) }}">
                        @csrf
                        @if($isOptedOut)
                            <input type="hidden" name="action" value="opt_in">
                            <button type="submit" class="console-btn console-btn-primary w-full">Opt In to Product Updates</button>
                        @else
                            <input type="hidden" name="action" value="unsubscribe">
                            <button type="submit" class="console-btn console-btn-danger w-full">Unsubscribe from Marketing</button>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
