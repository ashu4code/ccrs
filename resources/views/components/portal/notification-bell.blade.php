{{-- Header notification bell. Used by the portal layout for both officials
     (web guard) and consumers (consumer guard). --}}
@props(['notifications', 'count'])

<details class="relative">
    <summary
        aria-label="Notifications, {{ $count }} unread"
        class="relative flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-md border border-gray-300 text-gray-700 marker:content-none hover:bg-gray-50 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/5"
    >
        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 18.75a2.25 2.25 0 0 1-4.5 0m9-2.25H5.25c1.2-1.2 1.5-2.7 1.5-5.25a5.25 5.25 0 0 1 10.5 0c0 2.55.3 4.05 1.5 5.25Z" />
        </svg>
        @if ($count > 0)
            <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-5 text-white">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </summary>

    <div class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/10 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-white/10 dark:text-gray-100">Notifications</h2>
        @forelse ($notifications as $headerNotification)
            <a href="{{ route('notifications.open', $headerNotification->id) }}" class="block border-b border-gray-100 px-4 py-3 last:border-b-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $headerNotification->data['title'] ?? 'Notification' }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $headerNotification->data['message'] ?? '' }}</span>
                <span class="mt-1 block text-[11px] text-gray-400 dark:text-gray-500">{{ $headerNotification->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400">No unread notifications.</p>
        @endforelse
    </div>
</details>
