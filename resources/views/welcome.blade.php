<x-layouts.portal title="HT-CGRF — Consumer Grievance Redressal Forum">
    @php
        $sessionParty = auth('consumer')->user() ?: auth()->user();
    @endphp

    {{-- Two-column login screen: welcome card (left) beside the sign-in area --}}
    <div class="mx-auto grid max-w-5xl gap-6 md:grid-cols-2 md:items-stretch">
        <section
            aria-labelledby="welcome-heading"
            class="rounded-lg bg-blue-900 p-6 text-white shadow-sm ring-1 ring-blue-950/20 dark:bg-blue-950 dark:ring-white/10"
        >
            <div class="flex items-center gap-3">
                <span
                    aria-hidden="true"
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold tracking-wide text-blue-900"
                >
                    CGRF
                </span>
                <p class="text-sm font-semibold leading-tight text-blue-100">
                    HT Consumer<br>Grievance Redressal Forum
                </p>
            </div>

            <h1 id="welcome-heading" class="mt-6 text-xl font-bold italic leading-snug text-blue-100">
                Your Voice • Government's Responsibility
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-blue-100/80">
                Lodge an HT electricity grievance from your consumer account, watch it move
                from the regional manager to the competent authority, and receive a reasoned
                redressal — every step recorded on a public audit timeline.
            </p>

            <ul class="mt-6 space-y-3 text-sm">
                <li class="flex items-start gap-2">
                    <span aria-hidden="true" class="mt-0.5 font-bold text-emerald-300">✓</span>
                    Lodge complaints online
                </li>
                <li class="flex items-start gap-2">
                    <span aria-hidden="true" class="mt-0.5 font-bold text-emerald-300">✓</span>
                    Track status in real time
                </li>
                <li class="flex items-start gap-2">
                    <span aria-hidden="true" class="mt-0.5 font-bold text-emerald-300">✓</span>
                    Reasoned redressal by the competent authority
                </li>
            </ul>
        </section>

        @if ($sessionParty)
            {{-- Signed-in visitors get their portal card instead of the login card. --}}
            <section
                aria-labelledby="signed-in-heading"
                class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
            >
                <h2 id="signed-in-heading" class="text-xl font-bold">Welcome Back</h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    @if ($sessionParty instanceof \App\Models\Consumer)
                        Signed in as <span class="font-semibold">{{ $sessionParty->name }}</span>
                        @if ($sessionParty->consumer_number)
                            · <span class="font-mono">{{ $sessionParty->consumer_number }}</span>
                        @endif
                        — HT consumer account.
                    @else
                        Signed in as <span class="font-semibold">{{ $sessionParty->name }}</span>
                        @if ($role = $sessionParty->role)
                            · {{ $role->value === 'regional_manager' ? 'Relationship Manager' : 'Competent Authority' }}
                            @if ($sessionParty->region)
                                ({{ $sessionParty->region }} region)
                            @endif
                        @endif
                    @endif
                </p>

                <div class="mt-5">
                    <a
                        href="{{ route('dashboard') }}"
                        class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600"
                    >
                        My Complaints
                    </a>
                </div>
            </section>
        @else
            <x-auth.consumer-login-card />
        @endif
    </div>
</x-layouts.portal>
