@extends('emails.layout-text')

@section('content')
Payment Receipt

Hello,

Thank you for your payment. Your subscription payment for {{ $organization->name }} has been successfully processed.

Organisation: {{ $organization->name }}
Plan: {{ $plan->name }}
Amount Paid: {{ $currency }} {{ $amountFormatted }}
Payment Date: {{ $paymentDateFormatted }}
Reference: {{ $paymentReference }}

Go to your dashboard:
{{ route('dashboard') }}

Billing questions: {{ $billingEmail }}
@endsection
