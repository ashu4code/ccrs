<x-layouts.portal title="Edit Menu Item — HT-CGRF">
    <section aria-labelledby="edit-menu-heading" class="mb-6">
        <h1 id="edit-menu-heading" class="text-2xl font-bold text-gray-900 dark:text-gray-100">Edit Menu Item</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Update the sidebar entry. Leave both targets empty to keep it as a
            “Coming soon” placeholder.
        </p>
    </section>

    <form
        method="POST"
        action="{{ route('admin.menu.update', $item) }}"
        class="grid gap-4 rounded-lg max-w-3xl bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10 sm:grid-cols-2"
    >
        @csrf
        <div>
            <label for="audience" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Audience</label>
            <select
                id="audience"
                name="audience"
                required
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            >
                @foreach (App\Models\PortalMenuItem::audiences() as $audience)
                    <option value="{{ $audience }}" @selected($item->audience === $audience)>
                        {{ str_replace('_', ' ', Str::headline($audience)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="section" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Section</label>
            <input
                id="section"
                name="section"
                type="text"
                required
                maxlength="100"
                value="{{ old('section', $item->section) }}"
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
        </div>
        <div>
            <label for="label" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Label</label>
            <input
                id="label"
                name="label"
                type="text"
                required
                maxlength="100"
                value="{{ old('label', $item->label) }}"
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
        </div>
        <div>
            <label for="icon" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Icon</label>
            <select
                id="icon"
                name="icon"
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            >
                <option value="">(none)</option>
                @foreach (['dashboard', 'complaints', 'track', 'lodge', 'profile', 'help', 'sliders', 'shield'] as $icon)
                    <option value="{{ $icon }}" @selected(old('icon', $item->icon) === $icon)>
                        {{ Str::headline($icon) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="route_name" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                Named route <span class="font-normal">(optional)</span>
            </label>
            <input
                id="route_name"
                name="route_name"
                type="text"
                maxlength="120"
                placeholder="dashboard — leave empty for Coming soon"
                value="{{ old('route_name', $item->route_name) }}"
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
            @error('route_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="url" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                Path <span class="font-normal">(alternate target)</span>
            </label>
            <input
                id="url"
                name="url"
                type="text"
                maxlength="255"
                value="{{ old('url', $item->url) }}"
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
        </div>
        <div class="flex items-center gap-3 sm:col-span-2">
            <button
                type="submit"
                class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600"
            >
                Save changes
            </button>
            <a
                href="{{ route('admin.menu.index') }}"
                class="text-sm text-gray-600 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
            >
                Cancel
            </a>
        </div>
    </form>
</x-layouts.portal>
