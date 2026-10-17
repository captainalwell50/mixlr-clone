@extends('emails.affiliate-layout')

@section('content')
    <h1>Payout Disbursed Successfully</h1>

    <p>Hello {{ $user?->name ?? 'Partner' }},</p>

    <p>Good news! Your affiliate payout of <strong>{{ $payout->formattedAmount() }}</strong> has been disbursed to your designated bank account.</p>

    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Payout Reference:</td>
                <td style="color: #ffffff; font-family: monospace; font-size: 13px; text-align: right; padding: 4px 0;">{{ $payout->payout_reference }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Amount Paid:</td>
                <td style="color: #10b981; font-weight: 700; font-size: 15px; text-align: right; padding: 4px 0;">{{ $payout->formattedAmount() }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Date Settled:</td>
                <td style="color: #e2e8f0; font-size: 13px; text-align: right; padding: 4px 0;">{{ $payout->paid_at?->format('M j, Y') ?? now()->format('M j, Y') }}</td>
            </tr>
            @if ($payout->bank_name && $payout->maskedAccountNumber())
                <tr>
                    <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Destination:</td>
                    <td style="color: #e2e8f0; font-size: 13px; text-align: right; padding: 4px 0;">{{ $payout->bank_name }} ({{ $payout->maskedAccountNumber() }})</td>
                </tr>
            @endif
            @if ($payout->notes)
                <tr>
                    <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Settlement Note:</td>
                    <td style="color: #e2e8f0; font-size: 13px; text-align: right; padding: 4px 0;">{{ $payout->notes }}</td>
                </tr>
            @endif
        </table>
    </div>

    <p>All included commissions have been updated to Paid in your statement.</p>

    <div class="btn-wrapper">
        <a href="{{ $payoutsUrl }}" class="btn">View Payout Statement</a>
    </div>
@endsection
