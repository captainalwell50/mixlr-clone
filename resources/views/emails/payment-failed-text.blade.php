@extends('emails.layout-text')

@section('content')
Action Required: Payment attempt unsuccessful

Hello,

We were unable to process the recent subscription payment for {{ $organization->name }}.

Reason: {{ $safeReason }}
@if($plan)
Plan: {{ $plan->name }}
@endif

Please update your payment method to keep broadcasting:
{{ $retryUrl }}

Billing support: {{ $billingEmail }}
@endsection
