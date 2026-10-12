@extends('layouts.admin')

@section('title', 'New affiliate')

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">New affiliate</h1>
            <p class="console-lead">Attach an affiliate account to an existing user. This does not create a channel or a payout.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.affiliates.store') }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-300">User email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-zinc-300">Status</label>
            <select id="status" name="status" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                <option value="pending" @selected(old('status', 'pending') === 'pending')>Pending</option>
                <option value="invited" @selected(old('status') === 'invited')>Invited</option>
                <option value="approved" @selected(old('status') === 'approved')>Approved</option>
            </select>
        </div>
        <div>
            <label for="country_code" class="block text-sm font-medium text-zinc-300">Country code</label>
            <input id="country_code" type="text" name="country_code" value="{{ old('country_code') }}" required maxlength="2"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="NG">
        </div>
        <div>
            <label for="state_region" class="block text-sm font-medium text-zinc-300">State or region</label>
            <input id="state_region" type="text" name="state_region" value="{{ old('state_region') }}" required maxlength="120"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="city" class="block text-sm font-medium text-zinc-300">City</label>
            <input id="city" type="text" name="city" value="{{ old('city') }}" required maxlength="120"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="console-btn console-btn-primary">Create affiliate</button>
            <a href="{{ route('admin.affiliates.index') }}" class="console-btn console-btn-ghost">Back</a>
        </div>
    </form>
@endsection
