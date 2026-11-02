{{-- Official credential sign-in card — for Regional Managers and Competent Authorities. --}}
<section
    aria-labelledby="official-login-heading"
    class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
>
    <h2 id="official-login-heading" class="text-lg font-semibold">Official Login</h2>
    <p class="mb-5 mt-1 text-sm text-gray-500 dark:text-gray-400">
        For Relationship Managers and Competent Authorities.
    </p>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4" novalidate>
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Username or Email</label>
            <input
                id="email"
                name="email"
                type="text"
                autocomplete="username"
                value="{{ old('email') }}"
                required
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
            />
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
            Sign in
        </button>
    </form>

    <p class="mt-5 border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        Are you an electricity consumer?
        <a href="{{ route('home') }}" class="font-medium text-blue-700 hover:underline dark:text-blue-400">
            Consumer Login
        </a>
    </p>
</section>
