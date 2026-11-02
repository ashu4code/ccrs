@props(['actions', 'complaint' => null])

@php
    use App\Enums\UserRole;
    use App\Models\Consumer;

    $actionLabels = [
        'submitted' => 'Complaint Submitted',
        'handoff-rm' => 'Forwarded to Relationship Manager',
        'forwarded' => 'Forwarded to Competent Authority',
        'resolved' => 'Redressal Resolved',
    ];

    $roleLabels = [
        'consumer' => 'Consumer',
        UserRole::RegionalManager->value => 'Relationship Manager',
        UserRole::CompetentAuthority->value => 'Competent Authority',
    ];

    $roleBadgeClasses = [
        'consumer' => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10',
        UserRole::RegionalManager->value => 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-500/30',
        UserRole::CompetentAuthority->value => 'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-950 dark:text-violet-300 dark:ring-violet-500/30',
    ];

    $actionDotClasses = [
        'submitted' => 'bg-blue-600 dark:bg-blue-400',
        'handoff-rm' => 'bg-gray-400 dark:bg-gray-500',
        'forwarded' => 'bg-amber-500 dark:bg-amber-400',
        'resolved' => 'bg-green-600 dark:bg-green-500',
    ];

    // Attachments surfaced inline with their history entry: consumer files ride
    // along with the lodgement entry, authority files with the resolution entry.
    $entryAttachmentLabels = [
        'submitted' => 'Consumer attachments',
        'resolved' => 'Competent Authority attachments',
    ];
    $entryAttachments = [
        'submitted' => $complaint?->attachments ?? [],
        'resolved' => $complaint?->authority_attachments ?? [],
    ];

    // Display entries: each audit row renders once, and the lodgement entry
    // additionally surfaces its recorded hand-off to the relationship manager.
    $entries = [];
    foreach ($actions as $action) {
        $entries[] = ['entry' => 'action', 'action' => $action, 'display' => $action->action];

        if ($action->action === 'submitted' && $action->to_level === 'regional_manager') {
            $entries[] = ['entry' => 'handoff', 'action' => $action, 'display' => 'handoff-rm'];
        }
    }
@endphp

@if ($actions->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">
        No actions have been recorded on this case yet.
    </p>
@else
    <ol class="space-y-0" {{ $attributes }}>
        @foreach ($entries as $item)
            @php
                $entryAction = $item['action'];
                $actor = $entryAction->actor;

                $badgeKey = match (true) {
                    $item['entry'] === 'action' && $actor === null => null,
                    $item['entry'] === 'action' && $actor instanceof Consumer => 'consumer',
                    $item['entry'] === 'action' => $actor->role->value,
                    default => null,
                };
            @endphp

            <li class="relative pl-8 {{ $loop->last ? 'pb-0' : 'pb-8' }}">
                {{-- Rail line to the next entry (none after the last). --}}
                @unless ($loop->last)
                    <span
                        aria-hidden="true"
                        class="absolute top-6 bottom-0 left-[5px] w-0.5 bg-gray-200 dark:bg-gray-700"
                    ></span>
                @endunless

                <span
                    aria-hidden="true"
                    class="absolute top-1 left-0 mt-1.5 h-3 w-3 rounded-full ring-4 ring-gray-100 dark:ring-gray-900 {{
                        $actionDotClasses[$item['display']] ?? 'bg-gray-400 dark:bg-gray-500'
                    }}"
                ></span>

                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $actionLabels[$item['display']] ?? $item['display'] }}
                    </h3>

                    @if ($badgeKey !== null)
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $roleBadgeClasses[$badgeKey] }}"
                        >
                            {{ $actor->name }}
                            ·
                            {{ $roleLabels[$badgeKey] }}
                        </span>
                    @endif

                    <time
                        datetime="{{ $entryAction->created_at->toIso8601String() }}"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ $entryAction->created_at->format('d M Y, h:i A') }}
                    </time>
                </div>

                @if ($item['entry'] === 'action' && $entryAction->from_status && $entryAction->to_status !== $entryAction->from_status)
                    <p class="mt-1.5 flex items-center gap-1.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $entryAction->from_status }}</span>
                        <span aria-hidden="true">→</span>
                        <span>{{ $entryAction->to_status }}</span>
                    </p>
                @endif

                @if ($item['entry'] === 'action' && filled($entryAction->comment))
                    <blockquote
                        class="mt-2 border-l-2 border-gray-300 pl-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-400"
                    >
                        {{ $entryAction->comment }}
                    </blockquote>
                @endif

                @if ($item['entry'] === 'action' && filled($entryAttachments[$item['display']] ?? null))
                    @php
                        $entryPaths = $entryAttachments[$item['display']];
                    @endphp

                    <p class="mt-3 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {{ $entryAttachmentLabels[$item['display']] }} ({{ count($entryPaths) }})
                    </p>
                    <x-complaint.attachments :paths="$entryPaths" class="mt-1.5" />
                @endif
            </li>
        @endforeach
    </ol>
@endif
