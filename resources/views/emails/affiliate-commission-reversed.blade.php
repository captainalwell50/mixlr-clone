@extends('emails.affiliate-layout')

@section('content')
    <h1>Notice of Commission Adjustment</h1>

    <p>Hello {{ $user?->name ?? 'Partner' }},</p>

    <p>This is to inform you that a subscription payment previously attributed to your affiliate referral has been refunded or charged back. In accordance with programme rules, the corresponding commission has been adjusted.</p>

    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Payment Reference:</td>
                <td style="color: #ffffff; font-family: monospace; font-size: 13px; text-align: right; padding: 4px 0;">{{ $commission->payment_reference }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Adjusted Commission:</td>
                <td style="color: #f87171; font-weight: 700; font-size: 15px; text-align: right; padding: 4px 0;">{{ $commission->formattedCommissionAmount() }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Reason:</td>
                <td style="color: #e2e8f0; font-size: 13px; text-align: right; padding: 4px 0;">{{ $reason }}</td>
            </tr>
        </table>
    </div>

    <p>Your statement has been updated. Please note that if your qualification was previously earned, it remains permanently active under SoundMix rules.</p>

    <div class="btn-wrapper">
        <a href="{{ $statementUrl }}" class="btn">View Commission Statement</a>
    </div>
@endsection
