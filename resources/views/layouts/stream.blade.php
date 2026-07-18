<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d9b7a">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <main class="mx-auto flex max-w-lg flex-col gap-6 px-4 py-10">
        @yield('content')
    </main>
</body>
</html>
