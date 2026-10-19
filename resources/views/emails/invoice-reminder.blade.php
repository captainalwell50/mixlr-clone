@extends('emails.layout')

@section('content')
    <h1>Invoice Reminder</h1>

    <p>Hello,</p>

    <p>{{ $stageText }}</p>

    <div class="box">
        <table width="100%" cellpadding="6" cellspacing="0" style="color: #e2e8f0; font-size: 15px;">
            <tr>
                <td style="color: #94a3b8; width: 40%;">Invoice Number:</td>
                <td><strong>{{ $invoice->invoice_number }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Organisation:</td>
                <td>{{ $organization->name }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Amount Due:</td>
                <td><strong>{{ $invoice->currency }} {{ $amountFormatted }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Due Date:</td>
                <td>{{ $dueDateFormatted }}</td>
            </tr>
        </table>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $paymentLink }}" class="btn">Pay Invoice</a>
    </div>

    <p style="font-size: 14px; color: #94a3b8;">
        If you have already submitted payment, please allow a short time for verification or contact <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.
    </p>
@endsection
