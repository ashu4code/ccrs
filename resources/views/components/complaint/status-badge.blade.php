@props(['complaint'])

@php
    use App\Enums\ComplaintStatus;

    [$statusLabel, $statusBadgeClasses] = match ($complaint->status) {
        ComplaintStatus::Submitted => [
            'Submitted',
            'bg-amber-100 text-amber-800 ring-amber-300 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-500/40',
        ],
        ComplaintStatus::Forwarded => [
            'Escalated to Authority',
            'bg-blue-100 text-blue-800 ring-blue-300 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-500/40',
        ],
        ComplaintStatus::Resolved => [
            'Resolved',
            'bg-green-100 text-green-800 ring-green-300 dark:bg-green-950 dark:text-green-300 dark:ring-green-500/40',
        ],
    };
@endphp

<span
    class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusBadgeClasses }}"
    {{ $attributes }}
>
    {{ $statusLabel }}
</span>
