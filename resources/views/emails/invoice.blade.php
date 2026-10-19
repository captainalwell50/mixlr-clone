@extends('emails.layout')

@section('content')
    <h1>Invoice {{ $invoice->invoice_number }}</h1>

    <p>Hello,</p>

    <p>An invoice has been generated for <strong>{{ $organization->name }}</strong>.</p>

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
            @if($billingPeriod)
                <tr>
                    <td style="color: #94a3b8;">Billing Period:</td>
                    <td>{{ $billingPeriod }}</td>
                </tr>
            @endif
            <tr>
                <td style="color: #94a3b8;">Amount Due:</td>
                <td><strong>{{ $invoice->currency }} {{ $amountFormatted }}</strong></td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Due Date:</td>
                <td>{{ $dueDateFormatted }}</td>
            </tr>
            <tr>
                <td style="color: #94a3b8;">Status:</td>
                <td><span style="text-transform: uppercase; font-size: 13px; font-weight: 600; color: #38bdf8;">{{ $invoice->status }}</span></td>
            </tr>
        </table>
    </div>

    @if(!$invoice->isPaid())
        <div class="btn-wrapper">
            <a href="{{ $paymentLink }}" class="btn">Pay Invoice Online</a>
        </div>
    @endif

    <p style="font-size: 14px; color: #94a3b8;">
        For invoice enquiries or direct bank transfer confirmation, please contact <a href="mailto:{{ $billingEmail }}" style="color: #38bdf8;">{{ $billingEmail }}</a>.
    </p>
@endsection
