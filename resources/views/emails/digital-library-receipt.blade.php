@extends('emails.layout')

@section('content')
    <h1>Purchase Receipt</h1>

    <p>Hello {{ $user->name ?? 'Listener' }},</p>

    <p>Thank you for your purchase from the SoundMix Digital Library. Your transaction was successful, and your access has been activated immediately.</p>

    <div class="box">
        <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px; color: #cbd5e1;">
            <tr>
                <td style="color: #94a3b8; width: 40%;">Item / Series:</td>
                <td style="font-weight: 600; color: #ffffff;">{{ $series->title }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Amount Paid:</td>
                <td style="font-weight: 600; color: #38bdf8;">{{ $currency }} {{ $amountFormatted }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Payment Reference:</td>
                <td style="font-family: monospace; color: #e2e8f0;">{{ $providerReference }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Date & Time:</td>
                <td style="color: #e2e8f0;">{{ $paymentDateFormatted }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Access Level:</td>
                <td style="color: #34d399; font-weight: 600;">Permanent On-Demand Streaming</td>
            </tr>
        </table>
    </div>

    <p style="font-size: 14px; color: #cbd5e1;">
        Your purchase grants you permanent on-demand streaming access to all tracks and audio sessions included in this series through your SoundMix account.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $seriesUrl }}" class="btn">Stream Your Series Now</a>
    </div>

    <p style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        If you have any questions about this purchase or need playback assistance, please contact our support team at
        <a href="mailto:{{ $supportEmail }}" style="color: #38bdf8;">{{ $supportEmail }}</a>.
    </p>
@endsection
