<?php

namespace Database\Seeders;

use App\Enums\ComplaintLevel;
use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\ComplaintAction;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo consumers, demo officials for
     * each role, and one demo complaint per lifecycle stage (for the tracker).
     */
    public function run(): void
    {
        // Site admin — manages portal configuration such as sidebar menus.
        User::factory()->create([
            'name' => 'Site Admin',
            'email' => 'admin@ht-cgrf.test',
            'role' => UserRole::SiteAdmin->value,
        ]);

        // Consumers, counterpart of seeded officials: Consumer North works
        // against the whole North-region demo workflow.
        $consumer = Consumer::query()->create([
            'name' => 'Consumer North',
            'email' => 'consumer@ht-cgrf.test',
            'consumer_number' => 'CGRF-0001',
            'mobile' => '9000000001',
            'address' => '123 Industrial Area, North City',
            'load' => '100 kW',
            'meter_number' => 'MT-N-0001',
            'tariff' => 'HT-I',
            'region' => 'North',
            'discom_id' => null,
            'circle_id' => null,
            'division_id' => null,
            'subdivision_id' => null,
            'section_id' => null,
        ]);

        $consumerSouth = Consumer::query()->create([
            'name' => 'Consumer South',
            'email' => 'consumer-south@ht-cgrf.test',
            'consumer_number' => 'CGRF-0002',
            'mobile' => '9000000002',
            'address' => '456 Factory Road, South City',
            'load' => '150 kW',
            'meter_number' => 'MT-S-0002',
            'tariff' => 'HT-II',
            'region' => 'South',
            'discom_id' => null,
            'circle_id' => null,
            'division_id' => null,
            'subdivision_id' => null,
            'section_id' => null,
        ]);

        $manager = User::factory()->relationshipManager()->create([
            'name' => 'Relationship Manager North',
            'email' => 'rm-north@ht-cgrf.test',
        ]);

        User::factory()->relationshipManager('South')->create([
            'name' => 'Relationship Manager South',
            'email' => 'rm-south@ht-cgrf.test',
        ]);

        $authority = User::factory()->competentAuthority()->create([
            'name' => 'Competent Authority North',
            'email' => 'ca-north@ht-cgrf.test',
        ]);

        User::factory()->competentAuthority('South')->create([
            'name' => 'Competent Authority South',
            'email' => 'ca-south@ht-cgrf.test',
        ]);

        $this->seedDemoComplaint(
            $consumer,
            'Incorrect load meter reading on HT connection',
            ComplaintStatus::Submitted,
            ComplaintLevel::RegionalManager,
        );

        $this->seedDemoComplaint(
            $consumer,
            'Repeated unscheduled supply interruptions at feeder level',
            ComplaintStatus::Forwarded,
            ComplaintLevel::CompetentAuthority,
            ['manager' => $manager, 'authority' => $authority],
        );

        $this->seedDemoComplaint(
            $consumer,
            'Pollution surcharge billed despite industrial holiday scheme',
            ComplaintStatus::Resolved,
            ComplaintLevel::CompetentAuthority,
            ['manager' => $manager, 'authority' => $authority],
        );

        // South-region complaint so RM South has queue content too.
        $this->seedDemoComplaint(
            $consumerSouth,
            'Unauthorized disconnection during rectification work',
            ComplaintStatus::Submitted,
            ComplaintLevel::RegionalManager,
        );

        $this->call(PortalMenuSeeder::class);
    }

    /**
     * Create a demo complaint at the given stage with a matching audit trail.
     *
     * @param  array{manager?: User, authority?: User}  $actors
     */
    private function seedDemoComplaint(
        Consumer $consumer,
        string $subject,
        ComplaintStatus $status,
        ComplaintLevel $level,
        array $actors = [],
    ): Complaint {
        $complaint = Complaint::factory()->forConsumer($consumer)->create([
            'subject' => $subject,
            'description' => fake()->paragraphs(2, true),
        ]);

        $complaint->actions()->save($this->newAction(
            $complaint,
            $consumer,
            'submitted',
            null,
            ComplaintStatus::Submitted->value,
            null,
            ComplaintLevel::RegionalManager->value,
        ));

        if ($status !== ComplaintStatus::Submitted) {
            $complaint->forceFill([
                'status' => $status,
                'current_level' => $level,
                'regional_manager_id' => $actors['manager']->id,
                'competent_authority_id' => $actors['authority']->id,
                'resolved_at' => $status === ComplaintStatus::Resolved ? now() : null,
            ])->save();

            $complaint->actions()->save($this->newAction(
                $complaint,
                $actors['manager'],
                'forwarded',
                ComplaintStatus::Submitted->value,
                ComplaintStatus::Forwarded->value,
                ComplaintLevel::RegionalManager->value,
                ComplaintLevel::CompetentAuthority->value,
                'Escalated to the competent authority for adjudication.',
                $actors['authority'],
            ));
        }

        if ($status === ComplaintStatus::Resolved) {
            $complaint->actions()->save($this->newAction(
                $complaint,
                $actors['authority'],
                'resolved',
                ComplaintStatus::Forwarded->value,
                ComplaintStatus::Resolved->value,
                ComplaintLevel::CompetentAuthority->value,
                ComplaintLevel::CompetentAuthority->value,
                'Inspection completed; corrective measurements recorded and redressal ordered.',
            ));
        }

        return $complaint;
    }

    /**
     * Build an audit-trail action row (not yet persisted) attributed to the actor.
     */
    private function newAction(
        Complaint $complaint,
        Consumer|User $actor,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $fromLevel,
        string $toLevel,
        ?string $comment = null,
        ?User $taggedAuthority = null,
    ): ComplaintAction {
        $row = new ComplaintAction([
            'complaint_id' => $complaint->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_level' => $fromLevel,
            'to_level' => $toLevel,
            'comment' => $comment,
            'competent_authority_id' => $taggedAuthority?->id,
        ]);

        $row->actor()->associate($actor);

        return $row;
    }
}
