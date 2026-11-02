@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

@props(['paths'])

<ul {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
    @foreach ($paths as $path)
        <li>
            <a
                href="{{ Storage::disk('public')->url($path) }}"
                target="_blank"
                rel="noopener"
                class="inline-flex max-w-xs items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm text-blue-700 hover:bg-gray-200 dark:bg-white/5 dark:text-blue-400 dark:hover:bg-white/10"
            >
                <svg
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-4 w-4 shrink-0"
                >
                    <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                </svg>
                <span class="truncate">{{ Str::afterLast($path, '/') }}</span>
            </a>
        </li>
    @endforeach
</ul>
