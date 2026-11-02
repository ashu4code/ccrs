<x-layouts.portal title="Change password — HT-CGRF">
    <section
        aria-labelledby="password-change-heading"
        class="mx-auto w-full max-w-md rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
    >
        <h1 id="password-change-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Change password
        </h1>
        <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">
            @if ($consumer->password !== null)
                Choose a new permanent password for your consumer account
                <span class="font-mono">{{ $consumer->consumer_number }}</span>.
            @else
                You have not set a password on this account yet — choose one now, and it will
                work as an alternative to the OTP sign-in.
            @endif
        </p>

        @if (session('status'))
            <p class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ route('password.change.store') }}" class="space-y-4" novalidate>
            @csrf

            @if ($consumer->password !== null)
                <div>
                    <label for="current_password" class="mb-1 block text-sm font-medium">Current password</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
                    />
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">New password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm new password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
                />
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters. The new password must differ from the current one.</p>

            <button
                type="submit"
                class="w-full rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600"
            >
                Save new password
            </button>
        </form>
    </section>
</x-layouts.portal>
