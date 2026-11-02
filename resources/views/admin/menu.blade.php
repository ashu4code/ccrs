@php
    use App\Models\PortalMenuItem;
    use Illuminate\Support\Str;
@endphp

<x-layouts.portal title="Manage Menus — HT-CGRF">
    <section aria-labelledby="menu-heading" class="mb-6">
        <h1 id="menu-heading" class="text-2xl font-bold text-gray-900 dark:text-gray-100">Manage Menus</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Items render in the account sidebar of the matching audience. Items without a
            destination render as “Coming soon” placeholders.
        </p>
    </section>

    {{-- Create a new sidebar entry --}}
    <details class="mb-6 rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10">
        <summary class="cursor-pointer px-6 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
            Add a menu item
        </summary>
        <form method="POST" action="{{ route('admin.menu.store') }}" class="grid gap-4 px-6 pb-6 pt-2 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label for="new-audience" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Audience</label>
                <select id="new-audience" name="audience" required class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white">
                    @foreach ($audienceLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="new-section" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Section</label>
                <input id="new-section" name="section" type="text" required maxlength="100" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <label for="new-label" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Label</label>
                <input id="new-label" name="label" type="text" required maxlength="100" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <label for="new-icon" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Icon</label>
                <select id="new-icon" name="icon" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white">
                    <option value="">(none)</option>
                    @foreach (['dashboard', 'complaints', 'track', 'lodge', 'profile', 'help', 'sliders', 'shield'] as $icon)
                        <option value="{{ $icon }}">{{ Str::headline($icon) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="new-route" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Named route <span class="font-normal">(optional)</span></label>
                <input id="new-route" name="route_name" type="text" maxlength="120" placeholder="dashboard — leave empty for Coming soon" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white" />
                @error('route_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-url" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Path <span class="font-normal">(alternate target)</span></label>
                <input id="new-url" name="url" type="text" maxlength="255" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-white" />
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
                    Create item
                </button>
            </div>
        </form>
    </details>

    @foreach ($audienceLabels as $audience => $label)
        @php($items = $groups->get($audience, collect()))
        <section
            aria-labelledby="audience-{{ $audience }}-heading"
            class="mb-8 rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
        >
            <h2 id="audience-{{ $audience }}-heading" class="border-b border-gray-200 px-6 py-4 text-base font-semibold dark:border-white/10">
                {{ $label }} <span class="text-xs font-normal text-gray-500">({{ $items->count() }})</span>
            </h2>

            @if ($items->isEmpty())
                <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">No items yet — add one above.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-2">Order</th>
                                <th class="px-6 py-2">Section</th>
                                <th class="px-6 py-2">Label</th>
                                <th class="px-6 py-2">Destination</th>
                                <th class="px-6 py-2">State</th>
                                <th class="px-6 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="px-6 py-3 font-mono text-xs">{{ $item->sort_order }}</td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $item->section }}</td>
                                    <td class="px-6 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $item->label }}
                                        @if ($item->icon)
                                            <span class="ml-1 text-xs text-gray-400">({{ Str::headline($item->icon) }})</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ $item->route_name ?? ($item->url ?? 'coming soon') }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <span @class([
                                            'rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset',
                                            'bg-green-100 text-green-800 ring-green-300 dark:bg-green-950 dark:text-green-300 dark:ring-green-500/40' => $item->is_enabled,
                                            'bg-gray-100 text-gray-600 ring-gray-300 dark:bg-white/10 dark:text-gray-400 dark:ring-white/10' => ! $item->is_enabled,
                                        ])>
                                            {{ $item->is_enabled ? 'Enabled' : 'Hidden' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center justify-end gap-1 px-3 py-3">
                                            <form method="POST" action="{{ route('admin.menu.move', $item) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up" />
                                                <button type="submit" title="Move up" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-white/20 dark:hover:bg-white/5">↑</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.menu.move', $item) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down" />
                                                <button type="submit" title="Move down" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-white/20 dark:hover:bg-white/5">↓</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.menu.toggle', $item) }}">
                                                @csrf
                                                <button type="submit" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-white/20 dark:hover:bg-white/5">
                                                    {{ $item->is_enabled ? 'Hide' : 'Enable' }}
                                                </button>
                                            </form>
                                            <a href="{{ route('admin.menu.edit', $item) }}" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-white/20 dark:hover:bg-white/5">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('admin.menu.destroy', $item) }}">
                                                @csrf
                                                <button type="submit" class="rounded border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-950/40">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</x-layouts.portal>
