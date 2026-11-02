@props(['complaint'])

@php
    use App\Enums\ComplaintLevel;
    use App\Enums\ComplaintStatus;

    // Stage data derives ONLY from $complaint->status and $complaint->current_level.
    // Points 1..4 are marked "done" once the complaint has moved beyond them; the
    // point matching the complaint's current level is the amber "active" one.
    $doneUpTo = match ($complaint->status) {
        ComplaintStatus::Submitted => 1,
        ComplaintStatus::Forwarded => 2,
        ComplaintStatus::Resolved => 4,
    };

    $activeAt = $complaint->status === ComplaintStatus::Resolved
        ? null
        : match ($complaint->current_level) {
            ComplaintLevel::RegionalManager => 2,
            ComplaintLevel::CompetentAuthority => 3,
        };

    // Officers handling the case are shown on their pipeline stages; the
    // generic copy keeps meaning when the relation has not been set yet.
    $assignedOfficerName = $complaint->regionalManager?->name;
    $competentAuthorityName = $complaint->competentAuthority?->name;

    $steps = [
        [
            'label' => 'Complaint Lodged',
            'sub'   => 'Grievance registered on the portal.',
            'icon'  => 'document',
        ],
        [
            'label' => 'Under Relationship Manager Review',
            'sub'   => $assignedOfficerName !== null
                ? 'Assigned officer: ' . $assignedOfficerName . ' — is examining the case.'
                : 'Assigned officer is examining the case.',
            'icon'  => 'user',
        ],
        [
            'label' => 'Escalated to Competent Authority',
            'sub'   => $competentAuthorityName !== null
                ? 'Competent authority: ' . $competentAuthorityName . ' — for final adjudication.'
                : 'Forwarded for final adjudication.',
            'icon'  => 'arrow',
        ],
        [
            'label' => 'Redressal Resolved',
            'sub'   => 'Case closed with a resolution.',
            'icon'  => 'check',
        ],
    ];

    $states = [];
    foreach ($steps as $index => $step) {
        $position = $index + 1;

        $states[$index] = match (true) {
            $position <= $doneUpTo => 'done',
            $position === $activeAt => 'active',
            default => 'pending',
        };
    }

    $stateText = [
        'done'    => 'Completed',
        'active'  => 'Current stage',
        'pending' => 'Not yet reached',
    ];

    // Overall completion: every finished point is a 25% chunk.
    $progress = $doneUpTo === 4 ? 100 : $doneUpTo * 25;
    $progressText = $progress . '%';

    $icons = [
        'document' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'user' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'arrow' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'check' => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    ];

    $dotClasses = [
        'done'    => 'bg-green-600 text-white dark:bg-green-500',
        'active'  => 'bg-amber-500 text-white shadow-[0_0_0_6px_rgba(245,158,11,0.2)] dark:bg-amber-400 dark:shadow-[0_0_0_6px_rgba(251,191,36,0.2)]',
        'pending' => 'bg-gray-200 text-gray-500 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-400 dark:ring-white/10',
    ];

    $labelClasses = [
        'done'    => 'text-gray-900 dark:text-gray-100',
        'active'  => 'font-semibold text-amber-700 dark:text-amber-300',
        'pending' => 'text-gray-400 dark:text-gray-500',
    ];

    $subClasses = [
        'done'    => 'text-gray-500 dark:text-gray-400',
        'active'  => 'text-amber-600 dark:text-amber-400',
        'pending' => 'text-gray-400 dark:text-gray-500',
    ];
@endphp

{{-- Overall completion meter --}}
<div class="mb-8 flex items-center gap-4">
    <div
        class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
        role="progressbar"
        aria-label="Complaint completion"
        aria-valuenow="{{ $progress }}"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <div
            class="h-full rounded-full transition-all duration-700 ease-out {{ $progress === 100 ? 'bg-green-600 dark:bg-green-500' : 'bg-amber-500 dark:bg-amber-400' }}"
            style="width: {{ $progressText }};"
        ></div>
    </div>
    <p
        class="shrink-0 text-sm font-semibold {{ $progress === 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}"
        aria-hidden="true"
    >
        {{ $progressText }}
    </p>
</div>

<ol
    aria-label="Complaint progress"
    class="flex flex-col md:flex-row"
    {{ $attributes }}
>
    @foreach ($steps as $index => $step)
        @php
            $state = $states[$index];
            $isLast = $index === count($steps) - 1;
            $nextReached = ! $isLast && $states[$index + 1] !== 'pending';
            $connectorTone = $nextReached
                ? 'bg-green-600 dark:bg-green-500'
                : 'bg-gray-200 dark:bg-gray-700';
        @endphp

        <li
            class="relative pb-8 md:flex-1 md:pb-0 md:min-w-0"
            @if ($state === 'active')
                aria-current="step"
            @endif
        >
            {{-- Connector to the next point: grows down the left rail on mobile,
                 bridges the dot edges on desktop. --}}
            @unless ($isLast)
                <span
                    aria-hidden="true"
                    class="absolute left-[17px] top-10 bottom-0 w-0.5 md:hidden {{ $connectorTone }}"
                ></span>
                <span
                    aria-hidden="true"
                    class="absolute left-[calc(50%+1.25rem)] top-[19px] hidden h-0.5 w-[calc(100%-2.5rem)] md:block {{ $connectorTone }}"
                ></span>
            @endunless

            <div class="flex items-start gap-3.5 md:flex-col md:items-center md:gap-0">
                <div
                    aria-hidden="true"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full md:h-10 md:w-10 {{ $dotClasses[$state] }}"
                >
                    @if ($state === 'done')
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    @else
                        {!! $icons[$step['icon']] !!}
                    @endif
                </div>

                <div class="min-w-0 md:mt-3 md:max-w-52 md:text-center">
                    <p class="text-sm {{ $labelClasses[$state] }}">
                        {{ $step['label'] }}
                        <span class="sr-only"> — {{ $stateText[$state] }}</span>
                    </p>
                    <p class="mt-0.5 text-xs {{ $subClasses[$state] }}">
                        {{ $step['sub'] }}
                    </p>
                </div>
            </div>
        </li>
    @endforeach
</ol>

<p role="status" class="mt-6 border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
    @if ($complaint->status === ComplaintStatus::Resolved)
        <span class="inline-flex items-center gap-1.5 rounded-md bg-green-50 px-3 py-1.5 font-medium text-green-700 ring-1 ring-inset ring-green-200 dark:bg-green-950/40 dark:text-green-300 dark:ring-green-500/30">
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
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            This complaint has been resolved. Thank you for raising it on HT-CGRF.
        </span>
    @elseif ($activeAt !== null)
        Currently at stage: <span class="font-semibold text-amber-700 dark:text-amber-300">{{ $steps[$activeAt - 1]['label'] }}</span>.
    @else
        No active stage on the pipeline.
    @endif
</p>
