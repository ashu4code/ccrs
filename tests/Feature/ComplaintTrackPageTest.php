<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintTrackPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fetch the tracker page as the given party (consumer or official) and
     * return the raw HTML body.
     */
    private function trackBody(Consumer|User $party, Complaint $complaint): string
    {
        return (string) $this->actingAs(
            $party,
            $party instanceof Consumer ? 'consumer' : null,
        )
            ->get("/complaints/{$complaint->id}/track")
            ->assertOk()
            ->getContent();
    }

    /**
     * Attach the submission audit entry exactly as the register endpoint writes it.
     */
    private function addSubmissionAction(Complaint $complaint): void
    {
        $action = $complaint->actions()->make([
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'submitted',
            'from_level' => null,
            'to_level' => 'regional_manager',
        ]);
        $action->actor()->associate($complaint->consumer);
        $action->save();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();

        $this->get("/complaints/{$complaint->id}/track")->assertRedirect(route('login'));
    }

    public function test_foreign_consumer_is_forbidden(): void
    {
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();

        $foreignConsumer = Consumer::factory()->create(['region' => 'South']);

        $this->actingAs($foreignConsumer, 'consumer')
            ->get("/complaints/{$complaint->id}/track")
            ->assertForbidden();
    }

    public function test_cross_region_manager_is_forbidden(): void
    {
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();

        $foreignManager = User::factory()->regionalManager('South')->create();

        $this->actingAs($foreignManager)
            ->get("/complaints/{$complaint->id}/track")
            ->assertForbidden();
    }

    public function test_submitted_complaint_renders_regional_manager_review_active(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $this->addSubmissionAction($complaint);

        $html = $this->trackBody($consumer, $complaint);

        // All four pipeline points are rendered with the ticket number.
        $this->assertStringContainsString($complaint->ticket_number, $html);
        $this->assertStringContainsString('Complaint Lodged', $html);
        $this->assertStringContainsString('Under Relationship Manager Review', $html);
        $this->assertStringContainsString('Escalated to Competent Authority', $html);
        $this->assertStringContainsString('Redressal Resolved', $html);

        // Exactly one active point (regional manager review): one done, two pending.
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertSame(1, substr_count($html, ' — Completed'));
        $this->assertSame(1, substr_count($html, ' — Current stage'));
        $this->assertSame(2, substr_count($html, ' — Not yet reached'));

        // The timeline shows the consumer's submission entry.
        $this->assertStringContainsString('Complaint Submitted', $html);
        $this->assertStringContainsString('Forwarded to Relationship Manager', $html);
        $this->assertStringContainsString(e($consumer->name), $html);
    }

    public function test_forwarded_complaint_renders_authority_escalation_active(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();

        $complaint = Complaint::factory()
            ->forConsumer($consumer)
            ->create();
        $complaint->forceFill([
            'status' => 'forwarded',
            'current_level' => 'competent_authority',
            'regional_manager_id' => $manager->id,
            'competent_authority_id' => $authority->id,
        ])->save();
        $this->addSubmissionAction($complaint);

        $forwarded = $complaint->actions()->make([
            'action' => 'forwarded',
            'from_status' => 'submitted',
            'to_status' => 'forwarded',
            'from_level' => 'regional_manager',
            'to_level' => 'competent_authority',
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ]);
        $forwarded->actor()->associate($manager);
        $forwarded->save();

        $html = $this->trackBody($manager, $complaint);

        // One active point (authority escalation): two done, one pending.
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertSame(2, substr_count($html, ' — Completed'));
        $this->assertSame(1, substr_count($html, ' — Current stage'));
        $this->assertSame(1, substr_count($html, ' — Not yet reached'));

        // Timeline: forwarded entry with remark and the status transition.
        $this->assertStringContainsString('Forwarded to Relationship Manager', $html);
        $this->assertStringContainsString('Forwarded to Competent Authority', $html);
        $this->assertStringContainsString('Escalating for adjudication.', $html);
        $this->assertStringContainsString(e($manager->name), $html);
        $this->assertStringContainsString('→', $html);
    }

    public function test_resolved_complaint_has_all_steps_done_and_no_active_marker(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();

        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill([
            'status' => 'resolved',
            'current_level' => 'competent_authority',
            'regional_manager_id' => $manager->id,
            'competent_authority_id' => $authority->id,
            'resolved_at' => now(),
        ])->save();
        $this->addSubmissionAction($complaint);

        $forwarded = $complaint->actions()->make([
            'action' => 'forwarded',
            'from_status' => 'submitted',
            'to_status' => 'forwarded',
            'from_level' => 'regional_manager',
            'to_level' => 'competent_authority',
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ]);
        $forwarded->actor()->associate($manager);
        $forwarded->save();

        $resolved = $complaint->actions()->make([
            'action' => 'resolved',
            'from_status' => 'forwarded',
            'to_status' => 'resolved',
            'from_level' => 'competent_authority',
            'to_level' => 'competent_authority',
            'comment' => 'Meter was replaced after inspection.',
        ]);
        $resolved->actor()->associate($authority);
        $resolved->save();

        $html = $this->trackBody($authority, $complaint);

        // All four points done; no active marker remains.
        $this->assertSame(0, substr_count($html, 'aria-current="step"'));
        $this->assertSame(4, substr_count($html, ' — Completed'));
        $this->assertSame(0, substr_count($html, ' — Current stage'));
        $this->assertSame(0, substr_count($html, ' — Not yet reached'));

        // Timeline shows every entry, including remarks from the manager and the authority.
        $this->assertStringContainsString('Redressal Resolved', $html);
        $this->assertStringContainsString('Escalating for adjudication.', $html);
        $this->assertStringContainsString('Meter was replaced after inspection.', $html);
        $this->assertStringContainsString(e($authority->name), $html);

        // Resolution date is surfaced in the details grid.
        $this->assertStringContainsString('Resolved on', $html);
    }

    public function test_case_details_show_attachment_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('complaint-attachments/bill-copy.pdf', 'pdf-bytes');
        Storage::disk('public')->put('complaint-attachments/meter-photo.jpg', 'jpg-bytes');

        $consumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'attachments' => [
                'complaint-attachments/bill-copy.pdf',
                'complaint-attachments/meter-photo.jpg',
            ],
        ]);
        $this->addSubmissionAction($complaint);

        $html = $this->trackBody($consumer, $complaint);

        $this->assertStringContainsString('Attachments by consumer (2)', $html);
        $this->assertStringContainsString('bill-copy.pdf', $html);
        $this->assertStringContainsString('meter-photo.jpg', $html);
        $this->assertStringContainsString('/storage/complaint-attachments/bill-copy.pdf', $html);
        $this->assertStringContainsString('/storage/complaint-attachments/meter-photo.jpg', $html);
    }

    public function test_case_details_show_consumer_and_authority_attachments_separately(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('complaint-attachments/bill-copy.pdf', 'pdf-bytes');
        Storage::disk('public')->put('complaint-attachments/inspection-report.pdf', 'inspection-bytes');

        $consumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'attachments' => ['complaint-attachments/bill-copy.pdf'],
            'authority_attachments' => ['complaint-attachments/inspection-report.pdf'],
        ]);
        $this->addSubmissionAction($complaint);

        $html = $this->trackBody($consumer, $complaint);

        // Each uploader's files render under their own heading.
        $this->assertStringContainsString('Attachments by consumer (1)', $html);
        $this->assertStringContainsString('Attachments by Competent Authority (1)', $html);
        $this->assertStringContainsString('bill-copy.pdf', $html);
        $this->assertStringContainsString('inspection-report.pdf', $html);

        // Case history surfaces the attachments beside their own entries: the
        // consumer's files alongside the lodgement entry, and the authority's
        // files once the case reaches the resolved stage.
        $this->assertStringContainsString('Consumer attachments (1)', $html);
        $this->assertStringNotContainsString('Competent Authority attachments (1)', $html);
    }

    public function test_case_details_surface_full_consumer_details(): void
    {
        $consumer = Consumer::factory()->create([
            'email' => 'consumer@example.test',
            'mobile' => '9876543210',
            'address' => "Plot 12, Sector A\nKharar, Punjab",
            'load' => '350 kW',
            'meter_number' => 'MT-2201-7788',
            'tariff' => 'HT-II',
        ]);
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $this->addSubmissionAction($complaint);

        $html = $this->trackBody($consumer, $complaint);

        $this->assertStringContainsString('Consumer details', $html);
        $this->assertStringContainsString('consumer@example.test', $html);
        $this->assertStringContainsString('9876543210', $html);
        $this->assertStringContainsString('350 kW', $html);
        $this->assertStringContainsString('MT-2201-7788', $html);
        $this->assertStringContainsString('HT-II', $html);
        $this->assertStringContainsString(e('Plot 12, Sector A'), $html);
    }

    public function test_missing_consumer_details_render_a_dash(): void
    {
        $consumer = Consumer::factory()->create([
            'email' => null,
            'load' => null,
            'meter_number' => null,
            'tariff' => null,
        ]);
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $this->addSubmissionAction($complaint);

        $html = $this->trackBody($consumer, $complaint);

        // Dashed fallbacks: four unknown fields (email, load, meter, tariff)
        // plus five blank hierarchy levels (no DISCOM/Circle/Division/
        // Subdivision/Section assigned to the consumer).
        $this->assertSame(9, substr_count($html, 'text-gray-400 dark:text-gray-600">'));
    }

    public function test_assigned_competent_authority_sees_the_resolve_form(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();

        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'status' => 'forwarded',
            'current_level' => 'competent_authority',
        ]);
        $complaint->forceFill([
            'regional_manager_id' => $manager->id,
            'competent_authority_id' => $authority->id,
        ])->save();

        $html = $this->trackBody($authority, $complaint);

        $this->assertStringContainsString('Resolve and close', $html);
        $this->assertStringContainsString('Resolution remarks', $html);
        $this->assertStringContainsString('name="attachments[]"', $html);
        $this->assertStringContainsString('Add more attachment', $html);
        $this->assertStringContainsString('data-remove-row', $html);
    }

    public function test_competent_authority_resolves_with_remarks_and_attachments(): void
    {
        Storage::fake('public');

        $consumer = Consumer::factory()->create(['region' => 'North']);
        $authority = User::factory()->competentAuthority('North')->create();

        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'status' => 'forwarded',
            'current_level' => 'competent_authority',
        ]);
        $complaint->forceFill(['competent_authority_id' => $authority->id])->save();

        $this->actingAs($authority)->post("/complaints/{$complaint->id}/resolve", [
            'comment' => 'Meter replaced and bill corrected after site inspection.',
            'attachments' => [
                UploadedFile::fake()->create('inspection-report.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('meter-photo.jpg', 100, 'image/jpeg'),
            ],
        ])->assertRedirect(route('complaints.track', $complaint));

        $complaint->refresh();

        $this->assertSame('resolved', $complaint->status->value);
        $this->assertNotNull($complaint->resolved_at);

        // Authority-uploaded files land in their own column, leaving the
        // consumer's original attachments untouched.
        $this->assertCount(2, $complaint->authority_attachments);
        $this->assertNull($complaint->attachments);

        foreach ($complaint->authority_attachments as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // The audit trail records the remarks against the authority.
        $this->assertDatabaseHas('complaint_actions', [
            'complaint_id' => $complaint->id,
            'actor_type' => 'official',
            'actor_id' => $authority->id,
            'action' => 'resolved',
            'comment' => 'Meter replaced and bill corrected after site inspection.',
        ]);
    }

    public function test_foreign_competent_authority_cannot_resolve(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $authority = User::factory()->competentAuthority('North')->create();
        $otherAuthority = User::factory()->competentAuthority('North')->create();

        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'status' => 'forwarded',
            'current_level' => 'competent_authority',
        ]);
        $complaint->forceFill(['competent_authority_id' => $otherAuthority->id])->save();

        $this->actingAs($authority)
            ->postJson("/complaints/{$complaint->id}/resolve", ['comment' => 'Trying.'])
            ->assertStatus(403);

        $complaint->refresh();
        $this->assertSame('forwarded', $complaint->status->value);
    }

    public function test_resolve_rejects_disallowed_attachment_types(): void
    {
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create([
                'status' => 'forwarded',
                'current_level' => 'competent_authority',
            ]);
        $complaint->forceFill(['competent_authority_id' => $authority->id])->save();

        $this->actingAs($authority)->postJson("/complaints/{$complaint->id}/resolve", [
            'comment' => 'Attempt with binary payload.',
            'attachments' => [
                UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['attachments.0']);

        $complaint->refresh();
        $this->assertSame('forwarded', $complaint->status->value);
        $this->assertNull($complaint->authority_attachments);
    }
}
