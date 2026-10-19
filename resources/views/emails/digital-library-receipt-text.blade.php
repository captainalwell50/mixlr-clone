@extends('emails.layout-text')

@section('content')
PURCHASE RECEIPT - SOUNDMIX DIGITAL LIBRARY

Hello {{ $user->name ?? 'Listener' }},

Thank you for your purchase from the SoundMix Digital Library. Your transaction was successful, and your access has been activated immediately.

PURCHASE DETAILS:
- Item / Series: {{ $series->title }}
- Amount Paid: {{ $currency }} {{ $amountFormatted }}
- Payment Reference: {{ $providerReference }}
- Date: {{ $paymentDateFormatted }}
- Access Level: Permanent On-Demand Streaming

Your purchase grants you permanent on-demand streaming access to all tracks and audio sessions included in this series through your SoundMix account.

Stream Your Series Now:
{{ $seriesUrl }}

If you have any questions about this purchase or need playback assistance, please contact our support team at {{ $supportEmail }}.
@endsection
