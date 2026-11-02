{{-- Consumer OTP sign-in card — Sahyog-portal style, single centered card. --}}
<section
    aria-labelledby="consumer-login-heading"
    class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
>
    @if (session('otp.masked_mobile'))
        {{-- Step 2: OTP verification --}}
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">HT-CGRF</p>
        <h2 id="consumer-login-heading" class="text-xl font-bold">Welcome Back</h2>
        <p class="mb-5 mt-1 text-sm text-gray-500 dark:text-gray-400">
            Verify the one-time password sent to your registered mobile.
        </p>

        @php($codeHasError = $errors->has('code'))

        <form method="POST" action="{{ route('login.otp.verify') }}" class="space-y-4" novalidate>
            @csrf

            <p class="flex items-center gap-2 rounded-md bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950/60 dark:text-blue-200">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
                <span>
                    A one-time password was sent to
                    <strong class="font-mono">{{ session('otp.masked_mobile') }}</strong>.
                </span>
            </p>

            @if (session('otp.dev_code'))
                <div class="flex items-center justify-between gap-3 rounded-md border border-dashed border-amber-400 bg-amber-50 px-3 py-2 dark:border-amber-500/60 dark:bg-amber-950/40">
                    <p class="text-xs leading-snug text-amber-800 dark:text-amber-200">
                        <span class="font-semibold">Development notice:</span>
                        no SMS gateway is connected — tap the code to fill the boxes.
                    </p>
                    <button
                        type="button"
                        title="Fill this code into the boxes"
                        data-otp-fill="{{ session('otp.dev_code') }}"
                        class="shrink-0 cursor-pointer rounded-md bg-amber-100 px-2 py-1 pl-2.5 font-mono text-lg font-bold tracking-[0.3em] text-amber-900 transition hover:bg-amber-200 dark:bg-amber-900/60 dark:text-amber-100 dark:hover:bg-amber-800/60"
                    >{{ session('otp.dev_code') }}</button>
                </div>
            @endif

            <div>
                <label for="otp-box-1" class="mb-1.5 block text-sm font-medium">One-time password</label>
                <div class="flex gap-1.5 sm:gap-2.5" data-otp-group>
                    @for ($i = 1; $i <= 6; $i++)
                        <input
                            id="otp-box-{{ $i }}"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]"
                            maxlength="1"
                            autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}"
                            aria-label="One-time password digit {{ $i }} of 6"
                            value="{{ substr(old('code', ''), $i - 1, 1) }}"
                            class="h-[2rem] w-[2rem] shrink-0 rounded-md border bg-white text-center text-base font-semibold text-gray-900 outline-none transition focus:ring-2 sm:h-11 sm:w-11 sm:text-lg dark:bg-gray-800 dark:text-white {{ $codeHasError ? 'border-red-400 focus:border-red-500 focus:ring-red-500/30 dark:border-red-500/60' : 'border-gray-300 focus:border-blue-600 focus:ring-blue-600/30 dark:border-white/20' }}"
                        />
                    @endfor
                    <input type="hidden" name="code" value="" data-otp-value />
                </div>

                @error('code')
                    <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                @unless (session()->has('otp.id'))
                    <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                        The verification session has expired — <a href="{{ route('home', ['forget' => 1]) }}" class="underline">start again</a>.
                    </p>
                @endunless

                <noscript>
                    <style>[data-otp-group] { display: none; }</style>
                    <div class="mt-2">
                        <input
                            id="otp-code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                            class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-center text-lg tracking-[0.5em] focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Without JavaScript, type or paste all six digits here.</p>
                    </div>
                </noscript>
            </div>

            <div class="flex items-center justify-between">
                <a href="{{ route('home', ['forget' => 1]) }}" class="text-sm text-blue-700 hover:underline dark:text-blue-400">
                    Use another consumer ID
                </a>
                <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
                    Verify & sign in
                </button>
            </div>
        </form>
    @else
        {{-- Step 1: consumer identification --}}
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">HT-CGRF</p>
        <h2 id="consumer-login-heading" class="text-xl font-bold">Welcome Back</h2>
        <p class="mb-5 mt-1 text-sm text-gray-500 dark:text-gray-400">
            Verify with the one-time password sent to your registered mobile.
        </p>

        @error('code')
            <p class="mb-5 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
                {{ $message }}
            </p>
        @enderror

        <form method="POST" action="{{ route('login.otp.request') }}" class="space-y-4" novalidate>
            @csrf

            <div>
                <label for="consumer-number" class="mb-1 block text-sm font-medium">Consumer ID</label>
                <input
                    id="consumer-number"
                    name="consumer_number"
                    type="text"
                    autocomplete="username"
                    value="{{ old('consumer_number') }}"
                    required
                    class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/30 focus:outline-none dark:border-white/20 dark:bg-gray-800 dark:text-white"
                />
                @error('consumer_number')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">
                Send OTP
            </button>
        </form>
    @endif
</section>
