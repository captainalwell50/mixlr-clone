<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d9b7a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600|source-sans-3:400,500,600|open-sans:600,700,800" rel="stylesheet" />
    <title>@yield('title', config('app.name', 'Live Mix Audio'))</title>
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
</head>
<body class="stage-body">
    @yield('content')
</body>
</html>
