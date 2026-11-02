<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\Discom;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a full DISCOM → Circle → Division chain for a test consumer.
     */
    private function createDivision(): Division
    {
        $discom = Discom::create(['code' => 'D-TST', 'name' => 'Test Discom']);
        $circle = Circle::create(['code' => 'C-TST', 'name' => 'Test Circle', 'discom_id' => $discom->getKey()]);

        return Division::create(['code' => 'DV-TST', 'name' => 'Division-Test', 'circle_id' => $circle->getKey()]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_consumer_sees_their_own_complaints_and_stats(): void
    {
        $division = $this->createDivision();
        $consumer = Consumer::factory()->create([
            'region' => 'North',
            'division_id' => $division->getKey(),
            'email' => 'rani@example.test',
            'mobile' => '9877000000',
            'meter_number' => 'MT-1000-0001',
            'load' => '120 kW',
        ]);

        $open = Complaint::factory()->forConsumer($consumer)->create();
        Complaint::factory()->forConsumer($consumer)->create([
            'status' => ComplaintStatus::Resolved,
            'resolved_at' => now(),
        ]);

        // A complaint owned by another consumer never surfaces here.
        $foreign = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'South']))
            ->create();

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('My Complaints')
            ->assertSee("{$consumer->consumer_number} · Division-Test", false)
            // The complaint table groups by division, like the officials' queues.
            ->assertSee('Division')
            ->assertDontSee('Region')
            // Personal details render with their labelled grid…
            ->assertSee('Consumer details')
            ->assertSee('rani@example.test')
            ->assertSee('9877000000')
            ->assertSee('MT-1000-0001')
            // …and the lodgement action sits in the heading's right corner.
            ->assertSee('Register Complaint')
            ->assertSee(route('complaints.create'))
            ->assertSee('Total')
            ->assertSee('Open')
            ->assertSee('Resolved')
            ->assertSee($open->ticket_number)
            ->assertDontSee($foreign->ticket_number);
    }

    public function test_consumer_with_no_complaints_sees_the_empty_state(): void
    {
        $consumer = Consumer::factory()->create();

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('No complaints yet');
    }

    public function test_regional_manager_sees_complaints_assigned_to_them(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $division = $this->createDivision();

        $own = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North', 'division_id' => $division->getKey()]))
            ->create();
        $own->forceFill(['regional_manager_id' => $manager->id])->save();
        $other = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'South']))
            ->create();

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('Relationship Manager Queue — North', false)
            ->assertSee('Submitted')
            ->assertSee('Escalated')
            // Officials group by the consumer's division, not the region.
            ->assertSee('Division-Test')
            ->assertDontSee('Region')
            // Officials get no consumer profile card or lodgement shortcut.
            ->assertDontSee('Consumer details')
            ->assertDontSee('Register Complaint')
            ->assertSee($own->ticket_number)
            ->assertDontSee($other->ticket_number);
    }

    public function test_competent_authority_sees_their_assigned_complaints(): void
    {
        $authority = User::factory()->competentAuthority('North')->create();
        $manager = User::factory()->regionalManager('North')->create();

        $assigned = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->forwardedBy($manager, $authority)
            ->create();

        // Escalated to a different authority — invisible on this dashboard.
        $foreign = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->forwardedBy($manager, User::factory()->competentAuthority('North')->create())
            ->create();

        $this->actingAs($authority)->get('/dashboard')
            ->assertOk()
            ->assertSee('Assigned Complaints', false)
            ->assertSee('In Review')
            ->assertSee($assigned->ticket_number)
            ->assertDontSee($foreign->ticket_number);
    }

    public function test_site_admin_sees_all_complaints_across_regions(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        $north = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        $south = Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'South']))
            ->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('All Complaints — Administration', false)
            ->assertSee($north->ticket_number)
            ->assertSee($south->ticket_number);
    }

    public function test_site_admin_dashboard_renders_the_status_chart_and_fiscal_year_trend(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        // Two complaints inside the current financial year…
        Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create();
        Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'South']))
            ->create([
                'status' => ComplaintStatus::Resolved,
                'created_at' => now()->subMonths(6),
            ]);

        // …and one from a previous financial year, which must be excluded from the trend.
        Complaint::factory()
            ->forConsumer(Consumer::factory()->create(['region' => 'North']))
            ->create(['created_at' => now()->subYears(2)]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Complaint status')
            ->assertSee('Registrations per month')
            // The two in-FY complaints land in separate months, one each.
            ->assertSee('1 registered');
    }

    public function test_non_admin_dashboards_do_not_render_the_charts(): void
    {
        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Complaint status')
            ->assertDontSee('Registrations per month');

        $consumer = Consumer::factory()->create();

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Registrations per month');
    }
}
