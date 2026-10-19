@extends('emails.layout-text')

@section('content')
Invoice Reminder

Hello,

{{ $stageText }}

Invoice Number: {{ $invoice->invoice_number }}
Organisation: {{ $organization->name }}
Amount Due: {{ $invoice->currency }} {{ $amountFormatted }}
Due Date: {{ $dueDateFormatted }}

Pay invoice:
{{ $paymentLink }}

Billing enquiries: {{ $billingEmail }}
@endsection
