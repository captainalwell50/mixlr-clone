@extends('emails.affiliate-layout')

@section('content')
    <h1>Congratulations, {{ $user?->name ?? 'Partner' }}!</h1>

    <p>You have successfully referred <strong>5 distinct paying organisations</strong> to SoundMix Live. Under the SoundMix Affiliate Programme rules, your account has achieved <strong>Permanent Payout Qualification</strong>.</p>

    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <p style="margin: 0; color: #10b981; font-weight: 600; font-size: 15px;">✓ Qualification Status: Unlocked (Permanent)</p>
        <p style="margin: 6px 0 0 0; color: #94a3b8; font-size: 13px;">All matured commissions in your account are now released as payable balances.</p>
    </div>

    <p>You can now request manual payout runs directly from your partner portal whenever you have an available payable balance.</p>

    <div class="btn-wrapper">
        <a href="{{ $dashboardUrl }}" class="btn">Open Partner Dashboard</a>
    </div>

    <p style="color: #94a3b8; font-size: 13px;">Thank you for partnering with SoundMix Live to grow church and creator streaming across the globe.</p>
@endsection
