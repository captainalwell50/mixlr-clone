@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold text-white">Log in</h1>
        <p class="mt-1 text-sm text-zinc-400">Access your account or the admin console.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-300">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white placeholder-zinc-500 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-zinc-300">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-400">
                <input type="checkbox" name="remember" class="rounded border-zinc-600 bg-zinc-900 text-emerald-600 focus:ring-emerald-600">
                Remember me
            </label>
            <button type="submit"
                class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">
                Log in
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-500">
            No account?
            <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300">Register</a>
        </p>
    </div>
@endsection
