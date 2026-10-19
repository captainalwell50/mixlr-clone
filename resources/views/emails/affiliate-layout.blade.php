@extends('emails.layout', [
    'isAffiliate' => true,
    'replyTo' => 'partners@soundmix.live',
])

@section('content')
    @yield('content')
@endsection
