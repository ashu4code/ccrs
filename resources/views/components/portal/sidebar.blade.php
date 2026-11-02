@php
    use App\Models\PortalMenuItem;

    // Audience for the sidebar: officials take precedence over consumers.
    $webUser = auth()->user();
    if ($webUser && $webUser->role !== null) {
        $party = $webUser->role->value;
    } elseif (auth('consumer')->check()) {
        $party = PortalMenuItem::AUDIENCE_CONSUMER;
    } else {
        $party = null;
    }
@endphp

@if ($party === null)
    {{-- No account menu for guests — content stays full width. --}}
    <div class="min-w-0">
        {{ $slot }}
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-[16rem_1fr] lg:items-start">
        <aside aria-label="Account sidebar" class="lg:sticky lg:top-4">
            <nav class="rounded-lg bg-white p-4 ring-1 ring-gray-200 shadow-sm dark:bg-gray-950 dark:ring-white/10">
                @foreach (PortalMenuItem::menuFor($party)->groupBy('section') as $sectionItems)
                    <h3 class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ $sectionItems->first()->section }}
                    </h3>
                    <ul class="mb-3 space-y-0.5 last:mb-0">
                        @foreach ($sectionItems as $item)
                            @if ($item->displayUrl() === null)
                                <li
                                    aria-disabled="true"
                                    class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-gray-400 dark:text-gray-600"
                                >
                                    <span class="flex items-center gap-2">
                                        <x-portal.menu-item :icon="$item->icon" />
                                        {{ $item->label }}
                                    </span>
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">
                                        soon
                                    </span>
                                </li>
                            @else
                                <li>
                                    <a
                                        href="{{ $item->displayUrl() }}"
                                        @class([
                                            'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                                            'bg-blue-50 font-semibold text-blue-900 dark:bg-blue-950/60 dark:text-blue-200' => $item->isActive(),
                                            'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $item->isActive(),
                                        ])
                                    >
                                        <x-portal.menu-item :icon="$item->icon" />
                                        {{ $item->label }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
@endif
