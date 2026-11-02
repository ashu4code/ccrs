@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'HT-CGRF') }}</title>

        @fonts
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="flex min-h-full flex-col bg-gray-100 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        {{-- Utility strip: helpline and contact, Sahyog-portal style. --}}
        <div class="bg-gray-900 text-xs text-gray-300 dark:bg-black">
            <div class="mx-auto flex w-full max-w-full items-center justify-between gap-4 px-4 py-1.5">
                <p class="space-x-1">
                Helpline:
                <a href="mailto:support@ht-cgrf.test" class="font-medium text-white hover:underline">
                    support@ht-cgrf.test
                </a>
                <span class="text-gray-400">|</span>
                All Time: 8:00 AM – 8:00 PM (All Working Days)
            </p>
                <p aria-hidden="true">English</p>
            </div>
        </div>

        {{-- Masthead --}}
        <header class="border-b border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mx-auto flex w-full max-w-full items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-900 text-[10px] font-bold tracking-wide text-white dark:bg-blue-800">
                        CGRF
                    </span>
                    <div class="leading-tight">
                        <p class="text-base font-semibold">HT-CGRF Portal</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">HT Consumer Grievance Redressal Forum</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
            @if ($consumer = auth('consumer')->user())
                {{-- Consumers first: an explicit guard check keeps this branch
                     correct regardless of which guard is the auth default. --}}
                <x-portal.notification-bell :notifications="$headerNotifications" :count="$unreadNotificationCount" />
                <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
                    {{ $consumer->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:border-gray-400 dark:border-white/20 dark:hover:border-white/50">
                        Sign out
                    </button>
                </form>
            @elseif ($webUser = auth()->user())
                @if ($webUser->role === \App\Enums\UserRole::RegionalManager || $webUser->role === \App\Enums\UserRole::CompetentAuthority)
                    <x-portal.notification-bell :notifications="$headerNotifications" :count="$unreadNotificationCount" />
                @endif
                <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
                    {{ $webUser->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:border-gray-400 dark:border-white/20 dark:hover:border-white/50">
                        Sign out
                    </button>
                </form>
            @else
                {{-- Sahyog-portal header buttons — consumers sign in from the
                     home page card, officials on the dedicated login page. --}}
                <a
                    href="{{ route('home') }}"
                    @if (request()->routeIs('home')) aria-current="page" @endif
                    class="rounded-md border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:border-gray-400 dark:border-white/20 dark:text-gray-200 dark:hover:border-white/50"
                >
                    Consumer Login
                </a>
                <a
                    href="{{ route('login') }}"
                    @if (request()->routeIs('login')) aria-current="page" @endif
                    class="rounded-md bg-blue-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600"
                >
                    Official Login
                </a>
            @endif
        </div>
    </div>
</header>

{{-- Portal nav band — Sahyog RTMS style --}}
<nav
    aria-label="Portal sections"
    class="border-b border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
>
    <div class="mx-auto flex w-full max-w-full flex-wrap items-center gap-x-6 gap-y-1 px-4 py-2 text-sm">
        <a href="{{ route('home') }}" class="font-semibold hover:text-blue-700 dark:hover:text-blue-400">
            Home Page
        </a>
        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">|</span>
        @foreach (['FAQ', 'RTI', 'Privacy Policy', 'Accessibility Statement', 'Contact Us'] as $link)
            <a href="#" class="text-gray-600 hover:text-blue-700 hover:underline dark:text-gray-400 dark:hover:text-blue-400">
                {{ $link }}
            </a>
        @endforeach
    </div>
</nav>

        <main class="mx-auto w-full max-w-full flex-1 px-4 py-8">
            <x-portal.sidebar>
                {{ $slot }}
            </x-portal.sidebar>
        </main>

        {{-- Portal footer --}}
        <footer class="mt-12 bg-gray-900 text-gray-400 dark:bg-black">
            <div class="mx-auto grid w-full max-w-full gap-8 px-4 py-8 text-sm sm:grid-cols-3">
                <div>
                    <p class="mb-2 font-semibold text-gray-200">About this portal</p>
                    <p>HT-CGRF tracks HT electricity consumer grievances from lodgement through regional review to final redressal.</p>
                </div>
                <div>
                    <p class="mb-2 font-semibold text-gray-200">Contact</p>
                    <ul class="space-y-1">
                        <li>Helpline: 1800-180-0000 (prototype placeholder)</li>
                        <li><a href="mailto:support@ht-cgrf.test" class="hover:text-white">support@ht-cgrf.test</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-2 font-semibold text-gray-200">Quick links</p>
                    <ul class="space-y-1">
                        <li><a href="{{ url('/') }}" class="hover:text-white">Home</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white">Login</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10 px-4 py-4 text-center text-xs text-gray-500">
                © {{ date('Y') }} HT-CGRF · Design inspired by government grievance portals · All demo data is fictional.
            </div>
        </footer>
    </body>
</html>
