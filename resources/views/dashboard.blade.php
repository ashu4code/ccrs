@php
    use Illuminate\Support\Str;
@endphp

<x-layouts.portal title="Dashboard — HT-CGRF">
    {{-- Role-aware heading set by the controller --}}
    <section aria-labelledby="dashboard-heading" class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 id="dashboard-heading" class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $heading }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $subheading }}</p>
        </div>

        {{-- Right corner action: consumers lodge a complaint straight from their dashboard. --}}
        @if ($consumer ?? null)
            <a
                href="{{ route('complaints.create') }}"
                class="inline-flex items-center gap-2 rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600"
            >
                <svg
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-4 w-4"
                >
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                Register Complaint
            </a>
        @endif
    </section>

    {{-- Signed-in consumer's own details --}}
    @if ($consumer ?? null)
        @php
            $dashboardConsumerDetails = [
                'Name' => $consumer->name,
                'Consumer No.' => $consumer->consumer_number,
                'Email' => $consumer->email,
                'Mobile' => $consumer->mobile,
                'Meter No.' => $consumer->meter_number,
                'Load' => $consumer->load,
                'Tariff' => $consumer->tariff,
                'DISCOM' => $consumer->discom?->name,
                'Circle' => $consumer->circle?->name,
                'Division' => $consumer->division?->name,
                'Subdivision' => $consumer->subdivision?->name,
                'Section' => $consumer->section?->name,
            ];
        @endphp

        <section
            aria-labelledby="consumer-details-heading"
            class="mt-6 rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
        >
            <h2
                id="consumer-details-heading"
                class="border-b border-gray-200 px-6 py-4 text-base font-semibold text-gray-900 dark:border-white/10 dark:text-gray-100"
            >
                Consumer details
            </h2>
            <dl class="grid gap-x-8 gap-y-3 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($dashboardConsumerDetails as $label => $value)
                    <div>
                        <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm {{ filled($value) ? 'text-gray-900 font-mono dark:text-gray-100' : 'text-gray-400 dark:text-gray-600' }}">
                            {{ filled($value) ? $value : '-' }}
                        </dd>
                    </div>
                @endforeach
                @if (filled($consumer->address))
                    <div class="sm:col-span-2 lg:col-span-4">
                        <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Address</dt>
                        <dd class="mt-0.5 text-sm whitespace-pre-line text-gray-900 dark:text-gray-100">{{ $consumer->address }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    {{-- Aggregate cards --}}
    <div class="mt-6 grid gap-4 sm:[grid-template-columns:repeat(auto-fit,minmax(10rem,1fr))]">
        @foreach ($cards as $card)
            <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10">
                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($card['value']) }}</p>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Complaint status donut + FY registrations trend (administration only) --}}
    @if ($statusChart ?? null)
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section
                aria-labelledby="status-chart-heading"
                class="rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-baseline justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="status-chart-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Complaint status
                    </h2>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ number_format($statusChart['total']) }} total
                    </p>
                </div>

                @if ($statusChart['total'] === 0)
                    <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">
                        No complaints yet. The status mix will appear here once grievances are lodged.
                    </p>
                @else
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-4 px-6 py-6">
                        @php
                            $circumference = round(2 * M_PI * 40, 2);
                            $drawn = 0.0;
                        @endphp
                        <svg
                            viewBox="0 0 120 120"
                            class="-rotate-90 h-40 w-40 shrink-0"
                            role="img"
                            aria-label="Donut chart of complaints by status"
                        >
                            @foreach ($statusChart['slices'] as $slice)
                                @continue($slice['value'] === 0)
                                @php
                                    $arc = round($slice['value'] / $statusChart['total'] * $circumference, 2);
                                    $gap = round($circumference - $arc, 2);
                                    $offset = $drawn === 0.0 ? 0 : round(-$drawn, 2);
                                    $drawn += $arc;
                                @endphp
                                <circle
                                    cx="60"
                                    cy="60"
                                    r="40"
                                    fill="none"
                                    stroke-width="18"
                                    stroke-dasharray="{{ $arc }} {{ $gap }}"
                                    stroke-dashoffset="{{ $offset }}"
                                    class="{{ $slice['class'] }}"
                                />
                            @endforeach
                        </svg>

                        <ul class="grid gap-2 text-sm">
                            @foreach ($statusChart['slices'] as $slice)
                                <li class="flex items-center gap-2">
                                    <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full {{ $slice['dot'] }}"></span>
                                    <span class="min-w-28 text-gray-700 dark:text-gray-300">{{ $slice['label'] }}</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($slice['value']) }}</span>
                                    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ round($slice['value'] / $statusChart['total'] * 100) }}%
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section
                aria-labelledby="trend-chart-heading"
                class="rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="trend-chart-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Registrations per month
                    </h2>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $monthlyRegistrations['period'] }}</p>
                </div>

                @if ($monthlyRegistrations['total'] === 0)
                    <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">
                        No complaints registered in the current financial year.
                    </p>
                @else
                    @php
                        $width = 640;
                        $height = 240;
                        $padLeft = 40;
                        $padRight = 18;
                        $padTop = 26;
                        $padBottom = 36;
                        $plotWidth = $width - $padLeft - $padRight;
                        $plotHeight = $height - $padTop - $padBottom;
                        $baseline = $padTop + $plotHeight;
                        $peak = max(1, max(array_column($monthlyRegistrations['points'], 'value')));
                        $step = $plotWidth / (count($monthlyRegistrations['points']) - 1);
                        $coordinates = [];

                        foreach ($monthlyRegistrations['points'] as $index => $point) {
                            $coordinates[$index] = [
                                'x' => round($padLeft + $index * $step, 2),
                                'y' => round($baseline - $plotHeight * ($point['value'] / $peak), 2),
                                'point' => $point,
                            ];
                        }

                        $polyline = implode(' ', array_map(
                            fn (array $coordinate): string => $coordinate['x'].','.$coordinate['y'],
                            $coordinates,
                        ));
                    @endphp
                    <div class="px-4 py-4">
                        <svg
                            viewBox="0 0 {{ $width }} {{ $height }}"
                            class="w-full"
                            role="img"
                            aria-label="Line graph of complaints registered per month in the current financial year"
                        >
                            <line x1="{{ $padLeft }}" y1="{{ $padTop }}" x2="{{ $width - $padRight }}" y2="{{ $padTop }}" class="stroke-gray-200 dark:stroke-white/10" stroke-dasharray="4 6" />
                            <line x1="{{ $padLeft }}" y1="{{ round($padTop + $plotHeight / 2, 2) }}" x2="{{ $width - $padRight }}" y2="{{ round($padTop + $plotHeight / 2, 2) }}" class="stroke-gray-200 dark:stroke-white/10" stroke-dasharray="4 6" />
                            <line x1="{{ $padLeft }}" y1="{{ $baseline }}" x2="{{ $width - $padRight }}" y2="{{ $baseline }}" class="stroke-gray-300 dark:stroke-white/20" />

                            <text x="{{ $padLeft - 8 }}" y="{{ $padTop + 4 }}" text-anchor="end" font-size="11" class="fill-gray-500 dark:fill-gray-400">{{ $peak }}</text>
                            <text x="{{ $padLeft - 8 }}" y="{{ round($padTop + $plotHeight / 2 + 4, 2) }}" text-anchor="end" font-size="11" class="fill-gray-500 dark:fill-gray-400">{{ $peak / 2 }}</text>
                            <text x="{{ $padLeft - 8 }}" y="{{ $baseline + 4 }}" text-anchor="end" font-size="11" class="fill-gray-500 dark:fill-gray-400">0</text>

                            <polygon
                                points="{{ $padLeft }},{{ $baseline }} {{ $polyline }} {{ $coordinates[count($coordinates) - 1]['x'] }},{{ $baseline }}"
                                class="fill-blue-500/10"
                            />

                            <polyline
                                points="{{ $polyline }}"
                                fill="none"
                                stroke-width="2.5"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                                class="stroke-blue-500"
                            />

                            @foreach ($coordinates as $coordinate)
                                <circle
                                    cx="{{ $coordinate['x'] }}"
                                    cy="{{ $coordinate['y'] }}"
                                    r="4"
                                    stroke-width="2"
                                    class="fill-blue-500 stroke-white dark:stroke-gray-900"
                                >
                                    <title>{{ $coordinate['point']['label'] }} — {{ number_format($coordinate['point']['value']) }} registered</title>
                                </circle>
                            @endforeach

                            @foreach ($coordinates as $index => $coordinate)
                                @if ($index % 2 === 0)
                                    <text x="{{ $coordinate['x'] }}" y="{{ $baseline + 22 }}" text-anchor="middle" font-size="11" class="fill-gray-500 dark:fill-gray-400">
                                        {{ $coordinate['point']['label'] }}
                                    </text>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                @endif
            </section>
        </div>
    @endif

    {{-- Recent complaints with tracker links --}}
    <section
        aria-labelledby="recent-heading"
        class="mt-6 rounded-lg bg-white ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
    >
        <h2
            id="recent-heading"
            class="border-b border-gray-200 px-6 py-4 text-base font-semibold text-gray-900 dark:border-white/10 dark:text-gray-100"
        >
            Recent complaints
        </h2>

        @if ($complaints->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">
                No complaints yet. Any grievance you lodge will appear here with a live tracker link.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-2">Ticket</th>
                            <th class="px-6 py-2">Subject</th>
                            <th class="px-6 py-2">Category</th>
                            <th class="px-6 py-2">Status</th>
                            <th class="px-6 py-2">Division</th>
                            <th class="px-6 py-2">Lodged</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($complaints as $complaint)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="px-6 py-3">
                                    <a
                                        href="{{ route('complaints.track', $complaint) }}"
                                        class="font-mono text-xs font-semibold text-blue-700 hover:underline dark:text-blue-400"
                                    >
                                        {{ $complaint->ticket_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ $complaint->subject }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ Str::headline($complaint->category->value) }}</td>
                                <td class="px-6 py-3"><x-complaint.status-badge :complaint="$complaint" /></td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $complaint->consumer->division?->name ?? $complaint->region }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $complaint->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.portal>
