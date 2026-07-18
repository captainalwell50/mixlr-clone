@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold text-white">Create account</h1>
        <p class="mt-1 text-sm text-zinc-400">Optional listener accounts; broadcast access stays on signed studio links.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-zinc-300">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-300">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-zinc-300">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-300">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
            </div>
            <button type="submit"
                class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">
                Register
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-500">
            Already registered?
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300">Log in</a>
        </p>
    </div>
@endsection
