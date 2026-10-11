@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Members · '.$organization->name)

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="console-title">Members</h1>
            <p class="console-lead">{{ $organization->name }}</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('admin.organizations.customise', $organization) }}" class="text-zinc-400 hover:text-white">Customise</a>
            <a href="{{ route('admin.organizations.edit', $organization) }}" class="text-zinc-400 hover:text-white">Edit org</a>
        </div>
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
        <button type="submit" class="console-btn console-btn-primary">Add</button>
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
                        <td colspan="4" class="py-10 text-center text-zinc-400">
                            <svg class="h-8 w-8 text-zinc-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <p class="text-xs font-semibold text-zinc-300">No team members added yet.</p>
                            <p class="text-[11px] text-zinc-500 mt-0.5">Use the form above to invite co-hosts, media team members, or ministry leaders.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
