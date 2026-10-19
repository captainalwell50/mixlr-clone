@extends('emails.layout-text', ['isAffiliate' => true])

@section('content')
PAYOUT DISBURSED SUCCESSFULLY

Hello {{ $user?->name ?? 'Partner' }},

Good news! Your affiliate payout of {{ $payout->formattedAmount() }} has been disbursed to your designated bank account.

PAYOUT DETAILS:
- Payout Reference: {{ $payout->payout_reference }}
- Amount Paid: {{ $payout->formattedAmount() }}
- Date Settled: {{ $payout->paid_at?->format('M j, Y') ?? now()->format('M j, Y') }}
@if ($payout->bank_name && $payout->maskedAccountNumber())
- Destination: {{ $payout->bank_name }} ({{ $payout->maskedAccountNumber() }})
@endif
@if ($payout->notes)
- Settlement Note: {{ $payout->notes }}
@endif

All included commissions have been updated to Paid in your statement.

View Settlement Statement:
{{ $payoutsUrl }}
@endsection
