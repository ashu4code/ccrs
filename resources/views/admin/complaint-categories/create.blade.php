<x-layouts.portal title="Add Complaint Category">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Add Complaint Category</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Create a new complaint category.</p>
        </div>

        <form action="{{ route('admin.complaint-categories.store') }}" method="POST" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            @csrf

            <div>
                <label for="code" class="mb-1 block text-sm font-medium">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code') }}" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-600 focus:ring-blue-600 dark:border-white/20 dark:bg-gray-800">
                @error('code')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="mb-1 block text-sm font-medium">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-600 focus:ring-blue-600 dark:border-white/20 dark:bg-gray-800">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                <textarea name="description" id="description" rows="3" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-600 focus:ring-blue-600 dark:border-white/20 dark:bg-gray-800">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                <label for="is_active" class="ml-2 text-sm">Active</label>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">Create</button>
                <a href="{{ route('admin.complaint-categories.index') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.portal>
