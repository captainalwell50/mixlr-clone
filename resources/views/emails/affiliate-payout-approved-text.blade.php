@extends('emails.layout-text', ['isAffiliate' => true])

@section('content')
PAYOUT BATCH APPROVED

Hello {{ $user?->name ?? 'Partner' }},

Your requested affiliate payout batch has been reviewed and approved by platform administration.

PAYOUT DETAILS:
- Reference: {{ $payout->payout_reference }}
- Approved Amount: {{ $payout->formattedAmount() }}
- Status: Approved

Your payout has been approved and is scheduled for manual processing.

View Payout History:
{{ $payoutsUrl }}
@endsection
