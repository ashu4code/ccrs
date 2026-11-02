<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\PortalMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalMenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drop the migration-seeded baseline consumer rows so menu tests
     * control their own data.
     */
    private function resetConsumerAudienceMenu(): void
    {
        PortalMenuItem::query()->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)->delete();
    }

    public function test_menu_for_returns_enabled_items_in_sort_order(): void
    {
        $this->resetConsumerAudienceMenu();
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Second', 'sort_order' => 2]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'First', 'sort_order' => 1]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'Admin only']);

        $items = PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_CONSUMER);

        $this->assertSame(['First', 'Second'], $items->pluck('label')->all());
    }

    public function test_disabled_items_are_hidden_from_the_menu(): void
    {
        $this->resetConsumerAudienceMenu();
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->disabled()->create(['label' => 'Hidden', 'sort_order' => 1]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Visible', 'sort_order' => 2]);

        $this->assertSame(['Visible'], PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_CONSUMER)->pluck('label')->all());
    }

    public function test_items_created_without_sort_order_are_appended_to_their_audience(): void
    {
        $this->resetConsumerAudienceMenu();
        $first = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create();
        $second = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create();

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
    }

    public function test_a_stale_route_name_degrades_to_a_coming_soon_item(): void
    {
        $this->resetConsumerAudienceMenu();
        PortalMenuItem::factory()
            ->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)
            ->target('routes.were.removed')
            ->create(['label' => 'Dangling']);

        $item = PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_CONSUMER)->first();

        $this->assertNull($item->displayUrl());
        $this->assertFalse($item->isActive());
    }

    public function test_move_swaps_neighbors_within_the_audience_and_resequences(): void
    {
        $home = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'First', 'sort_order' => 1]);
        $menus = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'Second', 'sort_order' => 2]);
        $consumer = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Other audience', 'sort_order' => 5]);

        $menus->move('up');

        $this->assertSame(['Second', 'First'], PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_SITE_ADMIN)->pluck('label')->all());
        $this->assertSame(1, $menus->refresh()->sort_order);
        $this->assertSame(2, $home->refresh()->sort_order);
        $this->assertSame(5, $consumer->refresh()->sort_order);
    }

    public function test_move_beyond_the_edge_is_a_noop(): void
    {
        $first = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'First', 'sort_order' => 1]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Second', 'sort_order' => 2]);

        $this->assertFalse($first->move('up'));
        $this->assertSame(1, $first->refresh()->sort_order);
    }

    public function test_consumer_dashboard_sidebar_renders_items_but_not_other_audiences(): void
    {
        $consumer = Consumer::factory()->create();

        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->target('dashboard')->create(['label' => 'Home Base', 'sort_order' => 1]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Consumer Charter', 'sort_order' => 2]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'Site Secret']);

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('Home Base')
            ->assertSee(route('dashboard'), false)
            ->assertSee('Consumer Charter')
            ->assertSee('soon')
            ->assertDontSee('Site Secret');
    }

    public function test_guests_receive_no_sidebar_at_all(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-label="Account sidebar"', false);
    }

    public function test_officials_receive_their_own_audience_sidebar(): void
    {
        $manager = User::factory()->regionalManager('North')->create();

        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_REGIONAL_MANAGER)->target('dashboard')->create(['label' => 'Region Queue', 'sort_order' => 1]);
        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'Manage Menus']);

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('Region Queue')
            ->assertDontSee('Manage Menus');
    }

    public function test_site_admin_sidebar_links_to_menu_management(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN, 'Administration')->target('admin.menu.index')->create(['label' => 'Manage Menus', 'sort_order' => 1]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Manage Menus')
            ->assertSee(route('admin.menu.index'), false);
    }
}
