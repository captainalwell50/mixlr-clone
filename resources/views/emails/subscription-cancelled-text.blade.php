@extends('emails.layout-text')

@section('content')
Subscription Cancelled

Hello,

This is to confirm that the subscription for {{ $organization->name }} has been cancelled.

Your broadcast access will conclude at the end of your current active billing cycle.

To re-activate:
{{ $plansUrl }}

Billing questions: {{ $billingEmail }}
@endsection
