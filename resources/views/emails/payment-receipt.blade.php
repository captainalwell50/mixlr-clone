@extends('emails.layout')

@section('content')
    <h1>Payment Receipt</h1>

    <p>Hello,</p>

    <p>Thank you for your payment. Your subscription payment for <strong>{{ $organization->name }}</strong> has been successfully processed.</p>

    <div class="box">
        <table width="100%" cellpadding="6" cellspacing="0" style="color: #e2e8f0; font-size: 15px;">
            <tr>
                <td style="color: #94a3b8; width: 40%;">Organisation:</td>
                <td><strong>{{ $organization->name }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Plan:</td>
                <td><strong>{{ $plan->name }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Amount Paid:</td>
                <td><strong>{{ $currency }} {{ $amountFormatted }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Payment Date:</td>
                <td>{{ $paymentDateFormatted }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Reference:</td>
                <td><code style="background-color: #1e293b; padding: 2px 6px; border-radius: 4px; color: #38bdf8;">{{ $paymentReference }}</code></td>
            </tr>
        </table>
    </div>

    <div class="btn-wrapper">
        <a href="{{ route('dashboard') }}" class="btn">Go to Dashboard</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        For any billing questions or invoice enquiries, please contact <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.
    </p>
@endsection
