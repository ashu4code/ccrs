@props(['icon' => null])

@php
    $paths = match ($icon) {
        'dashboard' => ['M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z'],
        'complaints' => ['M4 6h16', 'M4 12h16', 'M4 18h10'],
        'track' => ['M3 12h4l3 8 4-16 3 8h4'],
        'lodge' => ['M12 5v14', 'M5 12h14'],
        'profile' => ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'],
        'help' => ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M9.1 10a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4', 'M12 17h.01'],
        'sliders' => ['M4 21v-7', 'M4 10V3', 'M12 21v-9', 'M12 8V3', 'M20 21v-5', 'M20 12V3', 'M1 14h6', 'M9 8h6', 'M17 16h6'],
        'shield' => ['M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z'],
        default => ['M4 6h16', 'M4 12h16', 'M4 18h16'],
    };
@endphp

<svg
    aria-hidden="true"
    class="h-4 w-4 shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
>
    @foreach ($paths as $path)
        <path d="{{ $path }}" />
    @endforeach
</svg>
