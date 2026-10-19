@extends('emails.layout')

@section('content')
    <h1>Subscription Activated</h1>

    <p>Hello,</p>

    <p>Your subscription for <strong>{{ $organization->name }}</strong> is now active on the <strong>{{ $plan->name }}</strong> plan!</p>

    <div class="box">
        <p style="margin: 0 0 6px 0;"><strong>Plan:</strong> {{ $plan->name }}</p>
        <p style="margin: 0;"><strong>Status:</strong> Active &bull; Unlimited high-fidelity broadcasting</p>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $dashboardUrl }}" class="btn">Launch Studio Dashboard</a>
    </div>

    <p>Thank you for partnering with SoundMix Live. If you need any assistance with setup or multi-channel routing, our support engineers are here to assist at <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.</p>
@endsection
