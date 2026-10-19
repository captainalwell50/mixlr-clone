@extends('emails.layout')

@section('content')
    <h1>Subscription Cancelled</h1>

    <p>Hello,</p>

    <p>This is to confirm that the subscription for <strong>{{ $organization->name }}</strong> has been cancelled.</p>

    <p>Your broadcast access will conclude at the end of your current active billing cycle. If this was intentional, we thank you for using SoundMix Live.</p>

    <div class="btn-wrapper">
        <a href="{{ $plansUrl }}" class="btn">View Plans & Re-activate</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        If you did not request this cancellation or have feedback to share, please reach out to our billing team at <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.
    </p>
@endsection
