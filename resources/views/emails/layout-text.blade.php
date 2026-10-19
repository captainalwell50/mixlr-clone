SOUNDMIX LIVE
========================================

@yield('content')

========================================
@if(!empty($isAffiliate))
Sent by SoundMix Live Affiliate Programme ({{ config('app.url') }})
Partner Support: partners@soundmix.live
@else
Sent by SoundMix Live ({{ config('app.url') }})
Support: {{ $replyTo ?? 'support@soundmix.live' }}
@endif
@if(!empty($unsubscribeUrl) && ($category ?? '') === 'marketing')
Unsubscribe: {{ $unsubscribeUrl }}
@endif
