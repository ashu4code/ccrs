<x-layouts.portal title="Complaint Categories">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Complaint Categories</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Manage complaint category master data.</p>
            </div>
            <a href="{{ route('admin.complaint-categories.create') }}" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
                Add Category
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">{{ $category->code }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">{{ $category->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $category->description }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 {{ $category->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-2">
                                <a href="{{ route('admin.complaint-categories.edit', $category) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Edit</a>
                                <form
    action="{{ route('admin.complaint-categories.destroy', $category) }}"
    method="POST"
    class="inline"
    data-confirm-delete
    data-confirm-title="Delete category"
    data-confirm-message="Permanently remove the complaint category “{{ $category->name }}”? This cannot be undone."
>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No complaint categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.confirm-delete-modal />
</x-layouts.portal>
