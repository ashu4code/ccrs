<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\User;
use App\Notifications\ComplaintLodgedNotification;
use App\Notifications\ComplaintResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lodging_notifies_assigned_manager_and_header_badge_clears_when_opened(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $otherManager = User::factory()->regionalManager('South')->create();
        $consumer = Consumer::factory()->create(['region' => 'North']);

        $this->actingAs($consumer, 'consumer')->post('/complaints', [
            'consumer_number' => $consumer->consumer_number,
            'category' => 'billing',
            'subject' => 'Incorrect bill amount',
            'description' => 'The bill amount does not match the meter reading.',
        ])->assertRedirect(route('dashboard'));

        $complaint = Complaint::query()->sole();
        $notification = $manager->notifications()->sole();

        $this->assertSame($complaint->id, $notification->data['complaint_id']);
        $this->assertSame(1, $manager->unreadNotifications()->count());
        $this->assertSame(0, $otherManager->notifications()->count());

        config()->set('auth.defaults.guard', 'web');
        auth()->forgetGuards();

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Notifications, 1 unread"', false)
            ->assertSee('New complaint lodged');

        $this->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('complaints.track', $complaint));

        $this->assertNotNull($notification->fresh()->read_at);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Notifications, 0 unread"', false)
            ->assertDontSee('New complaint lodged');
    }

    public function test_relationship_manager_cannot_open_another_managers_notification(): void
    {
        $assignedManager = User::factory()->regionalManager('North')->create();
        $otherManager = User::factory()->regionalManager('North')->create();
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill(['regional_manager_id' => $assignedManager->id])->save();

        $assignedManager->notify(new ComplaintLodgedNotification($complaint));
        $notification = $assignedManager->notifications()->sole();

        $this->actingAs($otherManager)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_resolution_notifies_the_consumer_and_badge_clears_when_opened(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $otherConsumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()
            ->forConsumer($consumer)
            ->forwardedBy($manager, $authority)
            ->create();

        $this->actingAs($authority)->postJson("/complaints/{$complaint->id}/resolve", [
            'comment' => 'Resolved after verification.',
        ])->assertOk();

        $notification = $consumer->notifications()->sole();

        $this->assertSame('Your complaint was resolved', $notification->data['title']);
        $this->assertSame($complaint->id, $notification->data['complaint_id']);
        $this->assertSame($complaint->ticket_number, $notification->data['ticket_number']);
        $this->assertSame(0, $otherConsumer->notifications()->count());
        $this->assertSame(0, $authority->unreadNotifications()->count());

        // A consumer session shares the cookie jar with the official acting as
        // in this scenario; flip the default guard so the consumer phase runs
        // with the consumer experience (dashboard, bell) as it really renders.
        config()->set('auth.defaults.guard', 'consumer');
        auth()->forgetGuards();

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Notifications, 1 unread"', false)
            ->assertSee('was resolved by the Competent Authority');

        $this->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('complaints.track', $complaint));

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertDontSee('was resolved by the Competent Authority');
    }

    public function test_forwarding_notifies_the_complaints_consumer(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();
        $consumer = Consumer::factory()->create(['region' => 'North']);
        $otherConsumer = Consumer::factory()->create(['region' => 'North']);
        $complaint = Complaint::factory()->forConsumer($consumer)->create();
        $complaint->forceFill(['regional_manager_id' => $manager->id])->save();

        $this->actingAs($manager)->postJson("/complaints/{$complaint->id}/forward", [
            'competent_authority_id' => $authority->id,
            'comment' => 'Escalating for adjudication.',
        ])->assertOk();

        $notification = $consumer->notifications()->sole();

        $this->assertSame('Complaint escalated for adjudication', $notification->data['title']);
        $this->assertSame($complaint->id, $notification->data['complaint_id']);
        $this->assertSame($complaint->ticket_number, $notification->data['ticket_number']);
        $this->assertSame(0, $otherConsumer->notifications()->count());
    }

    public function test_consumer_cannot_open_another_consumers_notification(): void
    {
        $otherConsumer = Consumer::factory()->create();
        $complaint = Complaint::factory()->forConsumer($otherConsumer)->create();

        $otherConsumer->notify(new ComplaintResolvedNotification($complaint));
        $notification = $otherConsumer->notifications()->sole();

        $this->actingAs(Consumer::factory()->create(), 'consumer')
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
