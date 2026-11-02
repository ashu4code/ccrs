<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintLevel;
use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\ComplaintAction;
use App\Models\Consumer;
use App\Models\User;
use App\Notifications\ComplaintEscalatedNotification;
use App\Notifications\ComplaintForwardedNotification;
use App\Notifications\ComplaintLodgedNotification;
use App\Notifications\ComplaintResolvedNotification;
use App\Rules\AttachmentFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GrievanceController extends Controller
{
    /**
     * Display the complaint queue for the signed-in party: their own
     * complaints for a consumer, role-scoped queues for officials.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $consumer = $request->user('consumer');

        if ($consumer !== null) {
            $complaints = $consumer->complaints();
        } else {
            $official = $request->user();

            abort_unless($official->role !== null, 403, 'The official has no role assigned.');

            $complaints = match ($official->role) {
                UserRole::RegionalManager => $official->managedComplaints(),
                UserRole::CompetentAuthority => $official->assignedComplaints(),
            };
        }

        return ComplaintResource::collection(
            $complaints
                ->with(['consumer', 'regionalManager', 'competentAuthority'])
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Display a complaint the viewer is permitted to see, with its audit trail.
     */
    public function show(Request $request, Complaint $complaint): ComplaintResource
    {
        $this->assertCanView($request, $complaint);

        $complaint->load($this->complaintDetailViewRelations());

        return new ComplaintResource($complaint);
    }

    /**
     * Render the browser tracker page — horizontal stepper pipeline plus the
     * historical action timeline — for a complaint the viewer may see.
     */
    public function track(Request $request, Complaint $complaint): View
    {
        $this->assertCanView($request, $complaint);

        $complaint->load($this->complaintDetailViewRelations());

        $competentAuthorities = collect();
        $official = $request->user();

        if (
            $official?->role === UserRole::RegionalManager
            && (int) $complaint->regional_manager_id === (int) $official->id
            && $complaint->status === ComplaintStatus::Submitted
        ) {
            $competentAuthorities = User::query()
                ->where('role', UserRole::CompetentAuthority)
                ->orderBy('name')
                ->get()
                ->sort(function (User $left, User $right) use ($complaint): int {
                    $divisionId = $complaint->consumer->division_id;

                    // Relevance order: same division, then same region,
                    // then the remaining authorities (name order within ties).
                    $score = fn (User $user): int => ($divisionId !== null && (int) $user->division_id === (int) $divisionId ? 2 : 0)
                        + ($user->region === $complaint->region ? 1 : 0);

                    return $score($right) <=> $score($left);
                })
                ->values();
        }

        return view('complaints.track', [
            'complaint' => $complaint,
            'competentAuthorities' => $competentAuthorities,
        ]);
    }

    /**
     * Relations hydrated for the API view and browser tracker page alike.
     *
     * @return (string|callable)[] Relations to eager load.
     */
    private function complaintDetailViewRelations(): array
    {
        return [
            'consumer.discom',
            'consumer.circle',
            'consumer.division',
            'consumer.subdivision',
            'consumer.section',
            'regionalManager',
            'competentAuthority',
            'actions' => fn ($query) => $query->with('actor')->oldest(),
        ];
    }

    /**
     * Enforce that the visitor may view the complaint: the consumer who owns
     * it, the assigned regional manager or competent authority, or a site
     * administrator (consumers take precedence when both guards hold a session).
     */
    private function assertCanView(Request $request, Complaint $complaint): void
    {
        $consumer = $request->user('consumer');
        $official = $request->user();

        $mayView = match (true) {
            $consumer !== null => (int) $complaint->consumer_id === (int) $consumer->id,
            $official?->role === UserRole::SiteAdmin => true,
            $official?->role === UserRole::RegionalManager => (int) $complaint->regional_manager_id === (int) $official->id,
            $official?->role === UserRole::CompetentAuthority => (int) $complaint->competent_authority_id === (int) $official->id,
            default => false,
        };

        abort_unless($mayView, 403, 'You cannot view this complaint.');
    }

    /**
     * Show the consumer complaint lodging form.
     */
    public function create(Request $request): View
    {
        $consumer = $request->user('consumer');

        abort_unless($consumer !== null, 403);

        return view('complaints.create', [
            'consumer' => $consumer,
            'categories' => ComplaintCategory::cases(),
        ]);
    }

    /**
     * Create a complaint on behalf of the authenticated consumer.
     */
    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $consumer = $request->user('consumer');

        $validated = $request->validate([
            'consumer_number' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::enum(ComplaintCategory::class)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'body' => ['nullable', 'string', 'max:20000'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => $this->attachmentRules(),
            'attachment' => $this->attachmentRules(),
        ], [
            'attachments.max' => 'You may attach up to five files.',
            'attachments.*.uploaded' => 'An attachment failed to upload. Each file must be 20 MB or smaller, with a total of no more than 100 MB.',
            'attachments.*.max' => 'Each attachment must be 20 MB or smaller.',
        ]);

        $description = $validated['description'] ?? ($validated['body'] ?? '');

        $consumer->loadMissing('division');

        // Assignment preference: the consumer's division manager, then the
        // region's manager, then any manager — a lean roster should never
        // block a consumer from lodging a complaint it must accept.
        $regionCandidate = $validated['region'] ?? $consumer->region;

        $regionalManager = $consumer->division_id === null
            ? null
            : User::query()
                ->where('role', UserRole::RegionalManager)
                ->where('division_id', $consumer->division_id)
                ->orderBy('id')
                ->first();

        $regionalManager ??= $regionCandidate === null
            ? null
            : User::query()
                ->where('role', UserRole::RegionalManager)
                ->where('region', $regionCandidate)
                ->orderBy('id')
                ->first();

        $regionalManager ??= User::query()
            ->where('role', UserRole::RegionalManager)
            ->orderBy('id')
            ->first();

        abort_unless(
            $regionalManager !== null,
            422,
            'No Relationship Manager is assigned to your division.',
        );

        $region = $validated['region']
            ?? $consumer->region
            ?? $regionalManager->region
            ?? $consumer->division?->name;

        abort_unless($region !== null && $region !== '', 422, 'A region or division is required to register a complaint.');

        $complaint = DB::transaction(function () use ($consumer, $regionalManager, $validated, $region, $description, $request): Complaint {
            $complaint = new Complaint([
                'consumer_number' => $validated['consumer_number'],
                'category' => $validated['category'],
                'subject' => $validated['subject'],
                'description' => $description,
                'region' => $region,
            ]);

            $complaint->consumer()->associate($consumer);
            $complaint->status = ComplaintStatus::Submitted;
            $complaint->current_level = ComplaintLevel::RegionalManager;
            $complaint->regionalManager()->associate($regionalManager);
            $complaint->save();

            $paths = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file && $file->isValid()) {
                        $paths[] = $file->store('complaint-attachments', 'public');
                    }
                }
            } elseif ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                if ($file && $file->isValid()) {
                    $paths[] = $file->store('complaint-attachments', 'public');
                }
            }
            if (! empty($paths)) {
                $complaint->forceFill(['attachments' => $paths])->save();
            }

            $this->recordAction($complaint, $consumer, [
                'action' => 'submitted',
                'from_status' => null,
                'to_status' => ComplaintStatus::Submitted->value,
                'from_level' => null,
                'to_level' => ComplaintLevel::RegionalManager->value,
            ]);

            $regionalManager->notify(new ComplaintLodgedNotification($complaint));

            return $complaint;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => new ComplaintResource($complaint),
                'message' => $this->lodgingStatusMessage($consumer, $regionalManager),
            ], 201);
        }

        return redirect()->route('dashboard')->with(
            'status',
            $this->lodgingStatusMessage($consumer, $regionalManager),
        );
    }

    /**
     * Success copy differing when the consumer's own division has no manager:
     * the case is absorbed by another division's manager in the interim.
     */
    private function lodgingStatusMessage(Consumer $consumer, User $regionalManager): string
    {
        $usedDivisionFallback = $consumer->division_id !== null
            && (int) $regionalManager->division_id !== (int) $consumer->division_id;

        return $usedDivisionFallback
            ? 'Soon a relationship manager of this division will contact you.'
            : 'Complaint lodged successfully.';
    }

    /**
     * Forward a submitted complaint assigned to the relationship manager to a
     * competent authority, preferring the same division as the complaint.
     */
    public function forwardToAuthority(Request $request, Complaint $complaint): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            (int) $complaint->regional_manager_id === (int) $user->id,
            403,
            'You can only forward complaints assigned to you.',
        );

        $complaint->loadMissing('consumer');

        $validated = $request->validate([
            'competent_authority_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::CompetentAuthority->value),
            ],
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $authority = User::query()->findOrFail($validated['competent_authority_id']);

        $divisionId = $complaint->consumer->division_id;

        // The same-division authority is preferred but never mandatory:
        // when a division or region has none mapped, the assigned manager
        // may escalate to any competent authority without a dead end.
        if ($divisionId !== null && (int) $authority->division_id !== (int) $divisionId) {
            $hasDivisionAuthority = User::query()
                ->where('role', UserRole::CompetentAuthority)
                ->where('division_id', $divisionId)
                ->whereKeyNot($authority->id)
                ->exists();

            abort_unless(
                ! $hasDivisionAuthority,
                422,
                'The competent authority must belong to the same division as the complaint.',
            );
        }

        if ($divisionId === null) {
            $hasRegionAuthority = User::query()
                ->where('role', UserRole::CompetentAuthority)
                ->where('region', $complaint->region)
                ->whereKeyNot($authority->id)
                ->exists();

            abort_unless(
                $authority->region === $complaint->region || ! $hasRegionAuthority,
                422,
                'The competent authority must belong to the same region as the complaint.',
            );
        }

        $complaint = DB::transaction(function () use ($complaint, $user, $authority, $validated): Complaint {
            // Re-read under a row lock so concurrent forwards cannot double-process the case.
            $locked = Complaint::query()->whereKey($complaint->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                (int) $locked->regional_manager_id === (int) $user->id,
                403,
                'You can only forward complaints assigned to you.',
            );
            abort_if($locked->status !== ComplaintStatus::Submitted, 409, 'The complaint has already been processed or resolved.');
            abort_if($locked->current_level !== ComplaintLevel::RegionalManager, 409, 'The complaint is no longer at the regional manager level.');

            $locked->status = ComplaintStatus::Forwarded;
            $locked->current_level = ComplaintLevel::CompetentAuthority;
            $locked->regional_manager_id = $user->id;
            $locked->competent_authority_id = $authority->id;
            $locked->save();

            $this->recordAction($locked, $user, [
                'action' => 'forwarded',
                'from_status' => ComplaintStatus::Submitted->value,
                'to_status' => ComplaintStatus::Forwarded->value,
                'from_level' => ComplaintLevel::RegionalManager->value,
                'to_level' => ComplaintLevel::CompetentAuthority->value,
                'competent_authority_id' => $authority->id,
                'comment' => $validated['comment'] ?? null,
            ]);

            $authority->notify(new ComplaintForwardedNotification($locked));

            // The consumer whose complaint moved forward learns its case is
            // now with the Competent Authority.
            $locked->loadMissing('consumer');
            $locked->consumer->notify(new ComplaintEscalatedNotification($locked));

            return $locked;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => new ComplaintResource($complaint),
            ]);
        }

        return redirect()->route('complaints.track', $complaint)
            ->with('status', 'Complaint forwarded to the Competent Authority.');
    }

    /**
     * Resolve a complaint escalated specifically to the authenticated competent authority.
     */
    public function resolveGrievance(Request $request, Complaint $complaint): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless((int) $complaint->competent_authority_id === (int) $user->id, 403, 'This complaint is assigned to a different competent authority.');

        $validated = $request->validate([
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => $this->attachmentRules(),
        ], [
            'attachments.max' => 'You may attach up to five files.',
            'attachments.*.uploaded' => 'An attachment failed to upload. Each file must be 20 MB or smaller, with a total of no more than 100 MB.',
            'attachments.*.max' => 'Each attachment must be 20 MB or smaller.',
        ]);

        $complaint = DB::transaction(function () use ($complaint, $user, $validated, $request): Complaint {
            // Re-read under a row lock so concurrent resolutions cannot double-process the case.
            $locked = Complaint::query()->whereKey($complaint->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === ComplaintStatus::Forwarded, 409, 'The complaint is not awaiting resolution.');
            abort_unless($locked->current_level === ComplaintLevel::CompetentAuthority, 409, 'The complaint is not at the competent authority level.');

            $locked->status = ComplaintStatus::Resolved;
            $locked->resolved_at = now();
            $locked->save();

            // Authority uploads are kept separate from the consumer's own attachments.
            $paths = $locked->authority_attachments ?? [];

            foreach ($request->file('attachments') ?? [] as $file) {
                if ($file && $file->isValid()) {
                    $paths[] = $file->store('complaint-attachments', 'public');
                }
            }

            if (count($paths) > count($locked->authority_attachments ?? [])) {
                $locked->forceFill(['authority_attachments' => $paths])->save();
            }

            $this->recordAction($locked, $user, [
                'action' => 'resolved',
                'from_status' => ComplaintStatus::Forwarded->value,
                'to_status' => ComplaintStatus::Resolved->value,
                'from_level' => ComplaintLevel::CompetentAuthority->value,
                'to_level' => ComplaintLevel::CompetentAuthority->value,
                'competent_authority_id' => $user->id,
                'comment' => $validated['comment'] ?? null,
            ]);

            // The consumer whose complaint was adjudicated learns about the redressal immediately.
            $locked->loadMissing('consumer');
            $locked->consumer->notify(new ComplaintResolvedNotification($locked));

            return $locked;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => new ComplaintResource($complaint),
            ]);
        }

        return redirect()->route('complaints.track', $complaint)
            ->with('status', 'Complaint resolved and closed.');
    }

    /**
     * Validation rules shared by every upload entry point (consumer lodging,
     * authority resolution): shape, size cap, and content-type guard.
     *
     * @return array<int|string, mixed>
     */
    private function attachmentRules(): array
    {
        return ['file', 'max:20480', new AttachmentFile];
    }

    /**
     * Append an audit-trail row for the actor (consumer or official) that performed it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordAction(Complaint $complaint, Consumer|User $actor, array $attributes): ComplaintAction
    {
        $action = new ComplaintAction($attributes);
        $action->actor()->associate($actor);

        $complaint->actions()->save($action);

        return $action;
    }
}
