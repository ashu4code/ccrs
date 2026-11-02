<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\Discom;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GrievanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `actingAs()` mutates the app-wide default guard (config
     * `auth.defaults.guard`), so without a reset a later unguarded
     * `actingAs($official)` would land the official on the consumer guard.
     * Call this between viewer swaps.
     */
    private function resetAuthState(): void
    {
        config()->set('auth.defaults.guard', 'web');
        auth()->forgetGuards();
    }

    private function createDivision(string $suffix): Division
    {
        $discom = Discom::query()->create([
            'code' => 'DISCOM-'.$suffix,
            'name' => 'Discom '.$suffix,
        ]);
        $circle = Circle::query()->create([
            'discom_id' => $discom->id,
            'code' => 'CIRCLE-'.$suffix,
            'name' => 'Circle '.$suffix,
        ]);

        return Division::query()->create([
            'circle_id' => $circle->id,
            'code' => 'DIVISION-'.$suffix,
            'name' => 'Division '.$suffix,
        ]);
    }

    public function test_consumer_registers_complaint_with_generated_elec_ticket(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $manager = User::factory()->regionalManager('North')->create();

        $response = $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => '1234567890',
            'category' => 'billing',
            'subject' => 'Incorrect bill amount',
            'description' => 'The bill amount does not match the meter reading.',
            'region' => 'North',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'submitted');
        $response->assertJsonPath('data.current_level', 'regional_manager');
        $response->assertJsonPath('data.region', 'North');

        $complaint = Complaint::query()->sole();

        $this->assertStringStartsWith('ELEC-', $complaint->ticket_number);
        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'consumer_id' => $consumer->id,
            'regional_manager_id' => $manager->id,
            'status' => 'submitted',
            'current_level' => 'regional_manager',
        ]);
        $this->assertDatabaseHas('complaint_actions', [
            'complaint_id' => $complaint->id,
            'actor_type' => 'consumer',
            'actor_id' => $consumer->id,
            'action' => 'submitted',
        ]);
    }

    public function test_consumer_complaint_is_assigned_to_division_manager_but_not_auto_forwarded(): void
    {
        $division = $this->createDivision('NORTH-1');
        $consumer = Consumer::factory()->create([
            'region' => null,
            'division_id' => $division->id,
        ]);
        $manager = User::factory()->regionalManager()->create([
            'division_id' => $division->id,
        ]);
        $authority = User::factory()->competentAuthority()->create([
            'division_id' => $division->id,
        ]);

        $response = $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'billing',
            'subject' => 'Division-specific complaint',
            'description' => 'The manager must review this before it goes to the authority.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.current_level', 'regional_manager');

        $complaint = Complaint::query()->sole();
        $this->assertSame($manager->id, $complaint->regional_manager_id);
        $this->assertNull($complaint->competent_authority_id);
        $this->assertSame($manager->region, $complaint->region);
        $this->assertDatabaseCount('complaint_actions', 1);
        $this->assertNotSame($manager->id, $authority->id);

        $this->resetAuthState();
        $this->actingAs($manager)
            ->get("/complaints/{$complaint->id}/track")
            ->assertOk()
            ->assertSee('Review and forward')
            ->assertSee($authority->name);

        $this->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
            'comment' => 'Reviewed by the division manager.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'forwarded')
            ->assertJsonPath('data.current_level', 'competent_authority');

        $complaint->refresh();
        $this->assertSame($manager->id, $complaint->regional_manager_id);
        $this->assertSame($authority->id, $complaint->competent_authority_id);
        $this->assertDatabaseHas('complaint_actions', [
            'complaint_id' => $complaint->id,
            'actor_type' => 'official',
            'actor_id' => $manager->id,
            'action' => 'forwarded',
            'comment' => 'Reviewed by the division manager.',
        ]);
    }

    public function test_consumer_cannot_lodge_complaint_without_a_division_manager(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);

        $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'billing',
            'subject' => 'No manager assigned',
            'description' => 'A manager must be assigned before lodging.',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'No Relationship Manager is assigned to your division.');

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_complaint_falls_back_to_another_division_manager_when_the_consumers_division_has_none(): void
    {
        $division = $this->createDivision('GAYA-1');
        $consumer = Consumer::factory()->create([
            'region' => null,
            'division_id' => $division->id,
        ]);

        // The only rostered manager serves a different division: lodging must
        // not dead-end for consumers of an unmanaged division.
        $manager = User::factory()->regionalManager()->create();

        $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'billing',
            'subject' => 'Unmanaged division complaint',
            'description' => 'Any rostered manager must absorb this case.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('message', 'Soon a relationship manager of this division will contact you.');

        $complaint = Complaint::query()->sole();
        $this->assertSame($manager->id, $complaint->regional_manager_id);
        $this->assertSame($manager->region, $complaint->region);
    }

    public function test_manager_escalates_to_any_authority_when_the_complaints_division_has_none(): void
    {
        $division = $this->createDivision('GAYA-2');
        $consumer = Consumer::factory()->create([
            'region' => null,
            'division_id' => $division->id,
        ]);
        $manager = User::factory()->regionalManager()->create();

        // A lone competent authority outside the complaint's division must
        // still be selectable rather than leaving the escalation stuck.
        $authority = User::factory()->competentAuthority()->create(['region' => 'South']);

        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'forwarded')
            ->assertJsonPath('data.current_level', 'competent_authority');

        $complaint->refresh();
        $this->assertSame($authority->id, $complaint->competent_authority_id);
    }

    public function test_manager_cannot_escalate_to_a_foreign_division_authority_when_the_division_has_one(): void
    {
        $division = $this->createDivision('GAYA-3');
        $consumer = Consumer::factory()->create([
            'region' => null,
            'division_id' => $division->id,
        ]);
        $manager = User::factory()->regionalManager()->create();
        User::factory()->competentAuthority()->create(['division_id' => $division->id]);

        // A same-division authority exists, so a cross-division pick stays a hard error.
        $foreignAuthority = User::factory()->competentAuthority()->create(['region' => 'South']);

        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $foreignAuthority->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The competent authority must belong to the same division as the complaint.');

        $complaint->refresh();
        $this->assertNull($complaint->competent_authority_id);
        $this->assertSame('submitted', $complaint->status->value);
    }

    public function test_browser_lodging_accepts_description_and_multiple_attachments(): void
    {
        Storage::fake('public');

        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $this->actingAs($consumer, 'consumer')->post('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Meter reading discrepancy',
            'description' => 'The displayed body is submitted directly as description.',
            'attachments' => [
                UploadedFile::fake()->create('meter-reading.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('supporting-document.pdf', 120, 'application/pdf'),
            ],
        ])->assertRedirect(route('dashboard'));

        $complaint = Complaint::query()->sole();

        $this->assertSame('The displayed body is submitted directly as description.', $complaint->description);
        $this->assertCount(2, $complaint->attachments);
        Storage::disk('public')->assertExists($complaint->attachments[0]);
        Storage::disk('public')->assertExists($complaint->attachments[1]);
    }

    public function test_browser_lodging_rejects_more_than_five_attachments(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $response = $this->actingAs($consumer, 'consumer')->post('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Meter reading discrepancy',
            'description' => 'Complaint details.',
            'attachments' => array_map(
                fn (int $index): UploadedFile => UploadedFile::fake()->create("evidence-{$index}.pdf", 10, 'application/pdf'),
                range(1, 6),
            ),
        ]);

        $response->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_lodging_rejection_names_the_file_and_the_detected_type(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $response = $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Unsupported attachment',
            'description' => 'The offending file must be identified for the consumer.',
            'attachments' => [
                UploadedFile::fake()->createWithContent('quarter-summary.txt', 'plain text contents'),
            ],
        ])->assertJsonValidationErrors(['attachments.0']);

        $message = (string) collect((array) $response->json('errors'))->flatten()->first();

        $this->assertStringContainsString('quarter-summary.txt', $message);
        $this->assertStringContainsString('text/plain', $message);
        $this->assertStringContainsString('Allowed:', $message);
    }

    public function test_lodging_accepts_a_docx_that_content_sniffs_as_an_archive(): void
    {
        Storage::fake('public');

        // A real DOCX is a ZIP container; on several PHP builds the content
        // sniffer reports its MIME as application/zip instead of the OOXML type.
        $document = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $document->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $document->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $document->addFromString('word/document.xml', '<p>Meter inspection details.</p>');
        $document->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $this->actingAs($consumer, 'consumer')->post('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Meter inspection report',
            'description' => 'A DOCX sniffed as a ZIP archive must still attach.',
            'attachments' => [
                UploadedFile::fake()->createWithContent('meter-inspection.docx', $bytes),
            ],
        ])->assertRedirect(route('dashboard'));

        $complaint = Complaint::query()->sole();

        $this->assertCount(1, $complaint->attachments);
        Storage::disk('public')->assertExists($complaint->attachments[0]);
    }

    public function test_lodging_rejects_empty_attachment_files(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $response = $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Skipping blank attachments',
            'description' => 'A zero-byte file is not a usable attachment.',
            'attachments' => [
                UploadedFile::fake()->create('damage-photo.jpg', 0, 'image/jpeg'),
            ],
        ])->assertJsonValidationErrors(['attachments.0']);

        $message = (string) collect((array) $response->json('errors'))->flatten()->first();

        $this->assertStringContainsString('damage-photo.jpg', $message);
        $this->assertStringContainsString('0 KB', $message);
    }

    public function test_consumer_can_lodge_complaint_with_valid_attachments_of_multiple_types(): void
    {
        Storage::fake('public');

        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $this->actingAs($consumer, 'consumer')->post('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Meter reading discrepancy',
            'description' => 'Complaint details.',
            'attachments' => [
                UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('letter.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            ],
        ])->assertRedirect(route('dashboard'));

        $complaint = Complaint::query()->sole();

        $this->assertCount(4, $complaint->attachments);
        foreach ($complaint->attachments as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_consumer_cannot_lodge_complaint_with_invalid_attachment_type(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        User::factory()->regionalManager('North')->create();

        $this->actingAs($consumer, 'consumer')->postJson('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'meter',
            'subject' => 'Meter reading discrepancy',
            'description' => 'Complaint details.',
            'attachments' => [
                UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['attachments.0']);

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_regional_manager_forwards_same_region_complaint(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $response = $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'forwarded');
        $response->assertJsonPath('data.current_level', 'competent_authority');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'forwarded',
            'regional_manager_id' => $manager->id,
            'competent_authority_id' => $authority->id,
        ]);
        $this->assertDatabaseHas('complaint_actions', [
            'complaint_id' => $complaint->id,
            'actor_type' => 'official',
            'actor_id' => $manager->id,
            'action' => 'forwarded',
        ]);
    }

    public function test_competent_authority_is_notified_when_complaint_is_forwarded(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $otherAuthority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ]);

        $notification = $authority->unreadNotifications()->sole();

        $this->assertSame('Complaint escalated to you', $notification->data['title']);
        $this->assertSame($complaint->id, $notification->data['complaint_id']);
        $this->assertSame($complaint->ticket_number, $notification->data['ticket_number']);
        $this->assertStringContainsString($complaint->ticket_number, $notification->data['message']);

        $this->assertSame(0, $otherAuthority->unreadNotifications()->count());
        $this->assertSame(0, $manager->unreadNotifications()->count());
    }

    public function test_competent_authority_notification_click_marks_it_read_and_opens_the_complaint(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
        ]);

        $notification = $authority->unreadNotifications()->sole();

        $this->actingAs($authority)
            ->get('/notifications/'.$notification->id)
            ->assertRedirect(route('complaints.track', $complaint));

        $this->assertSame(0, $authority->unreadNotifications()->count());
    }

    public function test_competent_authority_resolves_assigned_complaint(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->forwardedBy($manager, $authority)
            ->create();

        $response = $this->actingAs($authority)->postJson("/complaints/{$complaint->id}/resolve", [
            'comment' => 'Resolved after verification.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'resolved');

        $complaint->refresh();
        $this->assertNotNull($complaint->resolved_at);
        $this->assertDatabaseHas('complaint_actions', [
            'complaint_id' => $complaint->id,
            'actor_type' => 'official',
            'actor_id' => $authority->id,
            'action' => 'resolved',
        ]);
    }

    public function test_regional_manager_cannot_forward_complaint_of_other_region(): void
    {
        $manager = User::factory()->regionalManager('South')->create();
        $assignedManager = User::factory()->regionalManager('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $assignedManager->id])->save();

        $this->actingAs($manager)
            ->postJson("/complaints/{$complaint->id}/forward", [
                'competent_authority_id' => User::factory()->competentAuthority('North')->create()->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'submitted',
        ]);
    }

    public function test_regional_manager_cannot_resolve_complaint(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->forwardedBy($manager, $authority)
            ->create();

        $this->actingAs($manager)
            ->postJson("/complaints/{$complaint->id}/resolve", [
                'comment' => 'Trying to bypass the competent authority.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'forwarded',
        ]);
    }

    public function test_unassigned_competent_authority_cannot_resolve_complaint(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $otherAuthority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->forwardedBy($manager, $authority)
            ->create();

        // Same region, but the complaint was escalated to a different competent authority.
        $this->actingAs($otherAuthority)
            ->postJson("/complaints/{$complaint->id}/resolve")
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'forwarded',
        ]);
    }

    public function test_competent_authority_cannot_resolve_unforwarded_complaint(): void
    {
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['competent_authority_id' => $authority->id])->save();

        $this->actingAs($authority)
            ->postJson("/complaints/{$complaint->id}/resolve")
            ->assertStatus(409);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'submitted',
        ]);
    }

    public function test_forwarding_already_forwarded_complaint_conflicts(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $firstAuthority = User::factory()->competentAuthority('North')->create();
        $secondAuthority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $endpoint = "/complaints/{$complaint->id}/forward";

        $this->actingAs($manager)->postJson($endpoint, [
            'competent_authority_id' => $firstAuthority->id,
        ])->assertOk();

        $this->actingAs($manager)->postJson($endpoint, [
            'competent_authority_id' => $secondAuthority->id,
        ])->assertStatus(409);
    }

    public function test_consumer_cannot_forward_or_resolve_complaint(): void
    {
        // Forward/resolve are pinned to the `web` guard, so a consumer session
        // is simply unauthenticated there (not role-refused).
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $authority = User::factory()->competentAuthority('North')->create();
        $complaint = Complaint::factory()->forConsumer($consumer)->create();

        $this->actingAs($consumer, 'consumer')
            ->postJson("/complaints/{$complaint->id}/forward", [
                'competent_authority_id' => $authority->id,
            ])
            ->assertUnauthorized();

        $this->actingAs($consumer, 'consumer')
            ->postJson("/complaints/{$complaint->id}/resolve", [
                'comment' => 'Self-resolution attempt.',
            ])
            ->assertUnauthorized();
    }

    public function test_forward_validates_target_and_prefers_same_region_authority(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $complaint = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();
        $endpoint = "/complaints/{$complaint->id}/forward";

        // Missing target.
        $this->actingAs($manager)->postJson($endpoint, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['competent_authority_id']);

        // Target exists among officials but is not a competent authority.
        $this->actingAs($manager)->postJson($endpoint, [
            'competent_authority_id' => User::factory()->regionalManager('South')->create()->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['competent_authority_id']);

        // A same-region authority is mapped: foreign-region picks stay rejected.
        User::factory()->competentAuthority('North')->create();

        $foreignAuthority = User::factory()->competentAuthority('South')->create();

        $this->actingAs($manager)->postJson($endpoint, [
            'competent_authority_id' => $foreignAuthority->id,
        ])->assertStatus(422);

        // Without any same-region authority, escalation may cross regions
        // instead of leaving the complaint stranded at the manager level.
        User::query()
            ->where('role', UserRole::CompetentAuthority->value)
            ->where('region', 'North')
            ->delete();

        $this->actingAs($manager)->postJson($endpoint, [
            'competent_authority_id' => $foreignAuthority->id,
            'comment' => 'Region unmapped; escalating to the available authority.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'forwarded');

        $complaint->refresh();
        $this->assertSame($foreignAuthority->id, $complaint->competent_authority_id);
    }

    public function test_manager_may_forward_to_another_division_authority_when_the_complaints_division_has_none(): void
    {
        $complaintDivision = $this->createDivision('DIVISION-A');
        $otherDivision = $this->createDivision('DIVISION-B');
        $manager = User::factory()->regionalManager()->create([
            'division_id' => $complaintDivision->id,
        ]);
        $authority = User::factory()->competentAuthority()->create([
            'division_id' => $otherDivision->id,
        ]);
        $consumer = Consumer::factory()->create([
            'region' => null,
            'division_id' => $complaintDivision->id,
        ]);
        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'region' => $complaintDivision->name,
        ]);
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        // The complaint's division maps no authority, so the foreign-division
        // authority is the only escalation path and must be allowed.
        $this->actingAs($manager)
            ->postJson("/complaints/{$complaint->id}/forward", [
                'competent_authority_id' => $authority->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'forwarded');

        $complaint->refresh();
        $this->assertSame($authority->id, $complaint->competent_authority_id);
    }

    public function test_registration_validates_input(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);

        $this->actingAs($consumer, 'consumer')
            ->postJson('/complaints', [
                'category' => 'not-a-category',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consumer_number', 'category', 'subject', 'description']);
    }

    public function test_index_is_scoped_by_viewer(): void
    {
        $northConsumer = Consumer::factory()->create(['region' => 'North']);
        $southConsumer = Consumer::factory()->create(['region' => 'South']);

        $northComplaint = Complaint::factory()->forConsumer($northConsumer)->create();
        Complaint::factory()->forConsumer($southConsumer)->create();

        // Consumer sees only their own complaints.
        $this->actingAs($northConsumer, 'consumer')
            ->getJson('/complaints')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $northComplaint->id);

        $this->resetAuthState();
        // A regional manager sees only complaints assigned to them.
        $manager = User::factory()->regionalManager('North')->create();
        $northComplaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)
            ->getJson('/complaints')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $northComplaint->id);

        $this->resetAuthState();
        // Competent authority sees only complaints escalated to them.
        $authority = User::factory()->competentAuthority('North')->create();
        $assignedComplaint = Complaint::factory()
            ->forConsumer($northConsumer)
            ->forwardedBy(User::factory()->regionalManager('North')->create(), $authority)
            ->create();

        $this->actingAs($authority)
            ->getJson('/complaints')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedComplaint->id);
    }

    public function test_show_is_limited_to_owner_and_assigned_officials(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $otherConsumer = Consumer::factory()->create(['region' => 'South']);
        $manager = User::factory()->regionalManager('North')->create();
        $foreignManager = User::factory()->regionalManager('South')->create();
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($consumer, 'consumer')
            ->getJson("/complaints/{$complaint->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $complaint->id);

        $this->resetAuthState();
        $this->actingAs($manager)
            ->getJson("/complaints/{$complaint->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $complaint->id);

        $this->actingAs($otherConsumer, 'consumer')
            ->getJson("/complaints/{$complaint->id}")
            ->assertForbidden();

        $this->resetAuthState();
        $this->actingAs($foreignManager)
            ->getJson("/complaints/{$complaint->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/complaints', [
            'consumer_number' => '1234567890',
            'category' => 'billing',
            'subject' => 'Subject',
            'description' => 'Description',
        ])->assertUnauthorized();

        $this->getJson('/complaints')->assertUnauthorized();
    }
}
