@extends('emails.layout-text', ['isAffiliate' => true])

@section('content')
CONGRATULATIONS, {{ $user?->name ?? 'Partner' }}!

You have successfully referred 5 distinct paying organisations to SoundMix Live. Under the SoundMix Affiliate Programme rules, your account has achieved Permanent Payout Qualification.

✓ Qualification Status: Unlocked (Permanent)
All matured commissions in your account are now released as payable balances.

You can now request manual payout runs directly from your partner portal whenever you have an available payable balance.

Open Partner Dashboard:
{{ $dashboardUrl }}

Thank you for partnering with SoundMix Live to grow creator streaming across the globe.
@endsection
