@extends('emails.layout-text')

@section('content')
Subscription Activated

Hello,

Your subscription for {{ $organization->name }} is now active on the {{ $plan->name }} plan.

Plan: {{ $plan->name }}
Status: Active

Go to your studio dashboard:
{{ $dashboardUrl }}

Support enquiries: {{ $billingEmail }}
@endsection
