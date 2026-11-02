<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\PortalMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.menu.index'))->assertRedirect(route('login'));
    }

    public function test_officials_without_the_site_admin_role_are_forbidden(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $authority = User::factory()->competentAuthority('North')->create();

        foreach ([$manager, $authority] as $official) {
            $this->actingAs($official)->get(route('admin.menu.index'))->assertForbidden();
        }
    }

    public function test_site_admin_manages_the_menu_index(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Consumer Charter']);

        $this->actingAs($admin)->get(route('admin.menu.index'))
            ->assertOk()
            ->assertSee('Manage Menus')
            ->assertSee('Consumer Charter')
            ->assertSee('coming soon');
    }

    public function test_site_admin_stores_a_new_item(): void
    {
        $admin = User::factory()->siteAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.menu.store'), [
            'audience' => PortalMenuItem::AUDIENCE_SITE_ADMIN,
            'section' => 'Administration',
            'label' => 'Audit Log',
            'icon' => 'shield',
            'route_name' => null,
            'url' => null,
        ]);

        $response->assertRedirect(route('admin.menu.index'));

        $this->assertDatabaseHas('portal_menu_items', [
            'audience' => PortalMenuItem::AUDIENCE_SITE_ADMIN,
            'section' => 'Administration',
            'label' => 'Audit Log',
        ]);
    }

    public function test_an_unknown_route_name_is_rejected(): void
    {
        $admin = User::factory()->siteAdmin()->create();
        $before = PortalMenuItem::count();

        $this->actingAs($admin)
            ->from(route('admin.menu.index'))
            ->post(route('admin.menu.store'), [
                'audience' => PortalMenuItem::AUDIENCE_SITE_ADMIN,
                'section' => 'Administration',
                'label' => 'Broken Link',
                'route_name' => 'not.a.real.route',
            ])
            ->assertRedirect(route('admin.menu.index'))
            ->assertSessionHasErrors('route_name');

        $this->assertSame($before, PortalMenuItem::count());
    }

    public function test_site_admin_updates_an_item_via_the_edit_form(): void
    {
        $admin = User::factory()->siteAdmin()->create();
        $item = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Consumer Charter']);

        $this->actingAs($admin)->get(route('admin.menu.edit', $item))
            ->assertOk()
            ->assertSee('Consumer Charter');

        $this->actingAs($admin)->post(route('admin.menu.update', $item), [
            'audience' => PortalMenuItem::AUDIENCE_CONSUMER,
            'section' => 'Account',
            'label' => 'Consumer Charter v2',
            'icon' => 'help',
            'route_name' => 'dashboard',
            'url' => null,
        ])->assertRedirect(route('admin.menu.index'));

        $item->refresh();

        $this->assertSame('Consumer Charter v2', $item->label);
        $this->assertSame('Account', $item->section);
        $this->assertSame('dashboard', $item->route_name);
    }

    public function test_toggle_hides_and_restores_the_item(): void
    {
        $admin = User::factory()->siteAdmin()->create();
        $item = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Charter']);

        $this->actingAs($admin)->post(route('admin.menu.toggle', $item))->assertRedirect(route('admin.menu.index'));
        $this->assertFalse($item->refresh()->is_enabled);
        $this->assertNotContains('Charter', PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_CONSUMER)->pluck('label')->all());

        $this->actingAs($admin)->post(route('admin.menu.toggle', $item))->assertRedirect(route('admin.menu.index'));
        $this->assertTrue($item->refresh()->is_enabled);
    }

    public function test_move_orders_the_item_within_its_audience(): void
    {
        $admin = User::factory()->siteAdmin()->create();
        $first = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'First', 'sort_order' => 1]);
        $second = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_SITE_ADMIN)->create(['label' => 'Second', 'sort_order' => 2]);

        $this->actingAs($admin)
            ->post(route('admin.menu.move', $first), ['direction' => 'down'])
            ->assertRedirect(route('admin.menu.index'));

        $this->assertSame(['Second', 'First'], PortalMenuItem::menuFor(PortalMenuItem::AUDIENCE_SITE_ADMIN)->pluck('label')->all());
        $this->assertSame(1, $second->refresh()->sort_order);
        $this->assertSame(2, $first->refresh()->sort_order);
    }

    public function test_destroy_deletes_the_item(): void
    {
        $admin = User::factory()->siteAdmin()->create();
        $item = PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create();

        $this->actingAs($admin)
            ->post(route('admin.menu.destroy', $item))
            ->assertRedirect(route('admin.menu.index'));

        $this->assertDatabaseMissing('portal_menu_items', ['id' => $item->getKey()]);
    }

    public function test_a_missing_destination_renders_as_a_coming_soon_chip(): void
    {
        $consumer = Consumer::factory()->create();

        PortalMenuItem::factory()->forAudience(PortalMenuItem::AUDIENCE_CONSUMER)->create(['label' => 'Profile', 'sort_order' => 1]);

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('soon');
    }
}
