@extends('layouts.app')

@section('title', 'Organizations')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-white">Channels</h1>
        @if(auth()->user()->is_admin)
            <a href="{{ route('admin.organizations.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                New channel
            </a>
        @endif
    </div>

    <div class="mt-8 overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950/50">
                @forelse ($organizations as $org)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $org->name }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $org->slug }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.organizations.members', $org) }}" class="text-zinc-400 hover:text-white">Members</a>
                            <span class="text-zinc-600">·</span>
                            <a href="{{ route('admin.organizations.edit', $org) }}" class="text-emerald-400 hover:text-emerald-300">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-zinc-500">No organizations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $organizations->links() }}
    </div>
@endsection
