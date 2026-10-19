@extends('emails.layout')

@section('content')
    <h1>Payment Action Required</h1>

    <p>Hello,</p>

    <p>We were unable to process the recent subscription payment for <strong>{{ $organization->name }}</strong>.</p>

    <div class="box">
        <p style="margin: 0 0 8px 0; color: #f87171;"><strong>Reason:</strong> {{ $safeReason }}</p>
        @if($plan)
            <p style="margin: 0; color: #94a3b8;"><strong>Plan:</strong> {{ $plan->name }}</p>
        @endif
    </div>

    <p>To avoid any disruption to your live broadcasting capability, please review and update your payment method or complete payment using the link below:</p>

    <div class="btn-wrapper">
        <a href="{{ $retryUrl }}" class="btn">Update Payment Method</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        If you believe this was in error, please check with your bank or contact our billing desk directly at <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.
    </p>
@endsection
