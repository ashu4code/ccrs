<x-layouts.portal title="Create Role">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Role Mapping</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Map users to roles with DISCOM/Circle/Division/Subdivision/Section.</p>
            </div>
            <a href="{{ route('admin.roles.create') }}" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
                Create Role
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Organisation</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium">{{ $user->name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $user->display_name ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">{{ str_replace('_', ' ', $user->role?->value ?? '-') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                @php
                                    // Compact DISCOM → Circle → Division → Subdivision → Section chain;
                                    // one wrapping cell keeps the table from overflowing into a horizontal scrollbar.
                                    $organisation = collect([$user->discom?->name, $user->circle?->name, $user->division?->name, $user->subdivision?->name, $user->section?->name])
                                        ->filter()
                                        ->implode(' › ');
                                @endphp
                                {{ $organisation !== '' ? $organisation : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium space-x-2">
                                <a href="{{ route('admin.roles.edit', $user) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</a>
                                @if ($user->id !== auth()->id())
                                    <form
    action="{{ route('admin.roles.destroy', $user) }}"
    method="POST"
    class="inline"
    data-confirm-delete
    data-confirm-title="Delete user"
    data-confirm-message="Permanently remove {{ $user->name }} and their role mapping? This cannot be undone."
>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.confirm-delete-modal />
</x-layouts.portal>
