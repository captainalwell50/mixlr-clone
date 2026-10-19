@extends('emails.layout-text')

@section('content')
Invoice {{ $invoice->invoice_number }}

Hello,

An invoice has been generated for {{ $organization->name }}.

Invoice Number: {{ $invoice->invoice_number }}
Organisation: {{ $organization->name }}
@if($billingPeriod)
Billing Period: {{ $billingPeriod }}
@endif
Amount Due: {{ $invoice->currency }} {{ $amountFormatted }}
Due Date: {{ $dueDateFormatted }}
Status: {{ strtoupper($invoice->status) }}

@if(!$invoice->isPaid())
Pay online:
{{ $paymentLink }}
@endif

Billing support: {{ $billingEmail }}
@endsection
