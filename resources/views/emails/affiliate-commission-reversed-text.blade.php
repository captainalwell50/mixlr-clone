@extends('emails.layout-text', ['isAffiliate' => true])

@section('content')
NOTICE OF COMMISSION ADJUSTMENT

Hello {{ $user?->name ?? 'Partner' }},

This is to inform you that a subscription payment previously attributed to your affiliate referral has been refunded or charged back. In accordance with programme rules, the corresponding commission has been adjusted.

ADJUSTMENT DETAILS:
- Payment Reference: {{ $commission->payment_reference }}
- Adjusted Commission: {{ $commission->formattedCommissionAmount() }}
- Reason: {{ $reason }}

Your statement has been updated. Please note that if your qualification was previously earned, it remains permanently active under SoundMix rules.

View Commission Statement:
{{ $statementUrl }}
@endsection
