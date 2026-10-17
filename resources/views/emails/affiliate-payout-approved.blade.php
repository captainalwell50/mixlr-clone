@extends('emails.affiliate-layout')

@section('content')
    <h1>Payout Batch Approved</h1>

    <p>Hello {{ $user?->name ?? 'Partner' }},</p>

    <p>Your requested affiliate payout batch has been reviewed and <strong>approved</strong> by platform administration.</p>

    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Reference:</td>
                <td style="color: #ffffff; font-family: monospace; font-size: 13px; text-align: right; padding: 4px 0;">{{ $payout->payout_reference }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Approved Amount:</td>
                <td style="color: #38bdf8; font-weight: 700; font-size: 15px; text-align: right; padding: 4px 0;">{{ $payout->formattedAmount() }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8; font-size: 13px; padding: 4px 0;">Status:</td>
                <td style="color: #10b981; font-weight: 600; font-size: 13px; text-align: right; padding: 4px 0;">Approved</td>
            </tr>
        </table>
    </div>

    <p>Your payout has been approved and is scheduled for manual processing.</p>

    <div class="btn-wrapper">
        <a href="{{ $payoutsUrl }}" class="btn">View Payout History</a>
    </div>
@endsection
