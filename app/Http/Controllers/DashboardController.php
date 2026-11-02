<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Role-aware portal dashboard: a personal complaint summary for consumers,
     * an assigned division queue for regional managers, and the assigned queue for the
     * competent authority — each with aggregate cards and recent complaints.
     */
    public function index(Request $request): View
    {
        $official = $request->user();

        if ($official !== null && $official->role !== null) {
            $base = match ($official->role) {
                UserRole::SiteAdmin => Complaint::query(),
                UserRole::RegionalManager => $official->managedComplaints()->getQuery(),
                UserRole::CompetentAuthority => $official->assignedComplaints()->getQuery(),
                default => null,
            };

            if ($base !== null) {
                return match ($official->role) {
                    UserRole::RegionalManager => view('dashboard', [
                        'heading' => sprintf('Relationship Manager Queue — %s', $official->division?->name ?? $official->region ?? 'Unassigned Division'),
                        'subheading' => 'Complaints assigned to your division for review and forwarding.',
                        'cards' => [
                            ['label' => 'Assigned', 'value' => (clone $base)->count()],
                            ['label' => 'Submitted', 'value' => $this->countStatuses($base, [ComplaintStatus::Submitted])],
                            ['label' => 'Escalated', 'value' => $this->countStatuses($base, [ComplaintStatus::Forwarded])],
                            ['label' => 'Resolved', 'value' => $this->countStatuses($base, [ComplaintStatus::Resolved])],
                        ],
                        'complaints' => $this->recentComplaints($base),
                    ]),
                    UserRole::CompetentAuthority => view('dashboard', [
                        'heading' => 'Assigned Complaints',
                        'subheading' => 'Grievances escalated to you for review and reasoned redressal.',
                        'cards' => [
                            ['label' => 'Assigned', 'value' => (clone $base)->count()],
                            ['label' => 'In Review', 'value' => $this->countStatuses($base, [ComplaintStatus::Forwarded])],
                            ['label' => 'Resolved', 'value' => $this->countStatuses($base, [ComplaintStatus::Resolved])],
                        ],
                        'complaints' => $this->recentComplaints($base),
                    ]),
                    UserRole::SiteAdmin => view('dashboard', [
                        'heading' => 'All Complaints — Administration',
                        'subheading' => 'Every complaint across regions and lifecycle stages.',
                        'cards' => [
                            ['label' => 'Total', 'value' => (clone $base)->count()],
                            ['label' => 'Open', 'value' => $this->countStatuses($base, [ComplaintStatus::Submitted, ComplaintStatus::Forwarded])],
                            ['label' => 'Resolved', 'value' => $this->countStatuses($base, [ComplaintStatus::Resolved])],
                        ],
                        'statusChart' => $this->statusChart($base),
                        'monthlyRegistrations' => $this->monthlyRegistrations($base),
                        'complaints' => $this->recentComplaints($base),
                    ]),
                    default => abort(403, 'The official has no role assigned.'),
                };
            }
        }

        $consumer = $request->user('consumer');

        if ($consumer !== null) {
            $consumer->loadMissing('discom', 'circle', 'division', 'subdivision', 'section');
            $base = Complaint::query()->where('consumer_id', $consumer->getKey());

            return view('dashboard', [
                'heading' => 'My Complaints',
                'subheading' => sprintf('%s · %s', $consumer->consumer_number, $consumer->division?->name ?? $consumer->region),
                'consumer' => $consumer,
                'cards' => [
                    ['label' => 'Total', 'value' => (clone $base)->count()],
                    ['label' => 'Open', 'value' => $this->countStatuses($base, [ComplaintStatus::Submitted, ComplaintStatus::Forwarded])],
                    ['label' => 'Resolved', 'value' => $this->countStatuses($base, [ComplaintStatus::Resolved])],
                ],
                'complaints' => $this->recentComplaints($base),
            ]);
        }

        abort(403);
    }

    /**
     * Count complaints in the base queue filtered to the given statuses.
     *
     * @param  Builder<Complaint>  $base
     * @param  list<ComplaintStatus>  $statuses
     */
    private function countStatuses(Builder $base, array $statuses): int
    {
        return (clone $base)->where('status', $statuses)->count();
    }

    /**
     * Status distribution for the administration donut, tinted to match the status badges.
     *
     * @param  Builder<Complaint>  $base
     * @return array{total: int, slices: list<array{label: string, value: int, class: string, dot: string}>}
     */
    private function statusChart(Builder $base): array
    {
        $presentation = [
            ComplaintStatus::Submitted->value => ['label' => 'Submitted', 'class' => 'stroke-amber-500', 'dot' => 'bg-amber-500'],
            ComplaintStatus::Forwarded->value => ['label' => 'Escalated', 'class' => 'stroke-blue-500', 'dot' => 'bg-blue-500'],
            ComplaintStatus::Resolved->value => ['label' => 'Resolved', 'class' => 'stroke-green-500', 'dot' => 'bg-green-500'],
        ];

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get();

        $slices = [];

        foreach (ComplaintStatus::cases() as $status) {
            $meta = $presentation[$status->value];

            $slices[] = [
                'label' => $meta['label'],
                'value' => (int) $counts->firstWhere('status', $status)?->aggregate,
                'class' => $meta['class'],
                'dot' => $meta['dot'],
            ];
        }

        return [
            'total' => array_sum(array_column($slices, 'value')),
            'slices' => $slices,
        ];
    }

    /**
     * Complaints registered per month across the current financial year (April – March), zero-filled.
     *
     * @param  Builder<Complaint>  $base
     * @return array{period: string, total: int, points: list<array{label: string, value: int}>}
     */
    private function monthlyRegistrations(Builder $base): array
    {
        $firstMonth = Carbon::create(now()->month >= 4 ? now()->year : now()->year - 1, 4, 1);

        $counts = (clone $base)
            ->whereBetween('created_at', [$firstMonth, $firstMonth->copy()->addMonths(12)])
            ->pluck('created_at')
            ->groupBy(fn ($createdAt): string => Carbon::parse($createdAt)->format('Y-m'))
            ->map(fn ($month): int => $month->count());

        $points = [];

        foreach (range(0, 11) as $monthIndex) {
            $month = $firstMonth->copy()->addMonths($monthIndex);

            $points[] = [
                'label' => sprintf('%s %s', $month->format('M'), $month->format('y')),
                'value' => (int) $counts->get($month->format('Y-m'), 0),
            ];
        }

        return [
            'period' => sprintf('FY %d–%02d', $firstMonth->year, ($firstMonth->year + 1) % 100),
            'total' => array_sum(array_column($points, 'value')),
            'points' => $points,
        ];
    }

    /**
     * The most recent complaints of the base queue for the dashboard table.
     *
     * @param  Builder<Complaint>  $base
     * @return Collection<int, Complaint>
     */
    private function recentComplaints(Builder $base): Collection
    {
        return (clone $base)
            ->with(['consumer.division', 'regionalManager'])
            ->latest()
            ->limit(5)
            ->get();
    }
}
