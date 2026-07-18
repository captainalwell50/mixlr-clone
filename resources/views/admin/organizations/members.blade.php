@extends('layouts.app')

@section('title', 'Members · '.$organization->name)

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Members</h1>
            <p class="mt-1 text-sm text-zinc-400">{{ $organization->name }}</p>
        </div>
        <a href="{{ route('admin.organizations.edit', $organization) }}" class="text-sm text-zinc-400 hover:text-white">Edit org</a>
    </div>

    <form method="POST" action="{{ route('admin.organizations.members.store', $organization) }}" class="mt-8 max-w-xl space-y-4 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6">
        @csrf
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Add member</h2>
        <div>
            <label for="email" class="block text-sm text-zinc-300">Email</label>
            <input id="email" type="email" name="email" required value="{{ old('email') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
        </div>
        <div>
            <label for="name" class="block text-sm text-zinc-300">Name (if new user)</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
        </div>
        <div>
            <label for="role" class="block text-sm text-zinc-300">Role</label>
            <select id="role" name="role" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
                <option value="member">member — listen / org access</option>
                <option value="admin">admin — manage streams</option>
                <option value="owner">owner — manage members + streams</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add</button>
    </form>

    <div class="mt-10 overflow-hidden rounded-xl border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-left text-sm">
            <thead class="bg-zinc-900/80 text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $member->email }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.organizations.members.update', [$organization, $member]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="role" class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs text-white">
                                    @foreach (['owner','admin','member'] as $role)
                                        <option value="{{ $role }}" @selected($member->pivot->role === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs text-emerald-400">Save</button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.organizations.members.destroy', [$organization, $member]) }}" onsubmit="return confirm('Remove member?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-300 hover:text-red-200">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No members yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
