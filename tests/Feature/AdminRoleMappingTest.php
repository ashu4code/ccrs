<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Discom;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleMappingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Act as a signed-in site administrator.
     */
    private function siteAdmin(): User
    {
        return User::factory()->siteAdmin()->create();
    }

    public function test_create_page_renders_the_name_input(): void
    {
        $admin = $this->siteAdmin();

        $this->actingAs($admin)
            ->get('/admin/roles/create')
            ->assertOk()
            ->assertSee('name="display_name"', false)
            ->assertSee('<label for="display_name"', false);
    }

    public function test_store_persists_the_name_input_as_display_name(): void
    {
        $admin = $this->siteAdmin();

        $this->actingAs($admin)->post('/admin/roles', [
            'user_name' => 'Ashwin Kumar',
            'display_name' => 'Ashwin K.',
            'role' => 'relationship_manager',
            'region' => 'North',
        ])->assertRedirect(route('admin.roles.index'));

        $user = User::query()->where('name', 'Ashwin Kumar')->sole();

        $this->assertSame('Ashwin K.', $user->display_name);
        $this->assertSame('relationship_manager', $user->role?->value);
    }

    public function test_store_saves_null_display_name_when_the_name_input_is_omitted(): void
    {
        $admin = $this->siteAdmin();

        $this->actingAs($admin)->post('/admin/roles', [
            'user_name' => 'Plain User',
        ])->assertRedirect(route('admin.roles.index'));

        $user = User::query()->where('name', 'Plain User')->sole();

        $this->assertNull($user->display_name);
    }

    public function test_update_persists_the_name_input_as_display_name(): void
    {
        $admin = $this->siteAdmin();
        $manager = User::factory()->regionalManager('North')->create([
            'display_name' => 'Old Name',
        ]);

        $this->actingAs($admin)->put("/admin/roles/{$manager->id}", [
            'role_name' => $manager->name,
            'display_name' => 'New Name',
            'role' => 'relationship_manager',
        ])->assertRedirect(route('admin.roles.index'));

        $manager->refresh();

        $this->assertSame('New Name', $manager->display_name);
        $this->assertSame($manager->name, $manager->name);
    }

    public function test_update_persists_division_hierarchy_for_a_relationship_manager(): void
    {
        $admin = $this->siteAdmin();

        $discom = Discom::query()->create(['code' => 'DISCOM-X', 'name' => 'Discom X']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-X', 'name' => 'Circle X']);
        $division = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-X', 'name' => 'Division X']);

        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($admin)->put("/admin/roles/{$manager->id}", [
            'role_name' => $manager->name,
            'role' => 'relationship_manager',
            'division_id' => (string) $division->id,
        ])->assertRedirect(route('admin.roles.index'));

        $manager->refresh();

        $this->assertSame($division->id, $manager->division_id);
        $this->assertSame($circle->id, $manager->circle_id);
        $this->assertSame($discom->id, $manager->discom_id);
    }

    public function test_edit_page_renders_the_cascading_hierarchy_fields(): void
    {
        $admin = $this->siteAdmin();
        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($admin)
            ->get("/admin/roles/{$manager->id}/edit")
            ->assertOk()
            ->assertSee('name="circle_id"', false)
            ->assertSee('data-selected', false)
            ->assertSee('/admin/hierarchy/circles', false);

        $this->actingAs($admin)
            ->get('/admin/roles/create')
            ->assertOk()
            ->assertSee('/admin/hierarchy/sections', false);
    }

    public function test_index_shows_the_name_column(): void
    {
        $admin = $this->siteAdmin();
        $manager = User::factory()->regionalManager('North')->create([
            'display_name' => 'Shown Name',
        ]);

        $this->actingAs($admin)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee(e($manager->name), false)
            ->assertSee('Shown Name');
    }

    public function test_store_derives_the_region_from_the_selected_discom(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);

        $this->actingAs($admin)->post('/admin/roles', [
            'user_name' => 'Derived Region',
            'role' => 'relationship_manager',
            'discom_id' => (string) $discom->id,
        ])->assertRedirect(route('admin.roles.index'));

        $user = User::query()->where('name', 'Derived Region')->sole();

        $this->assertSame('North', $user->region);
        $this->assertSame($discom->id, $user->discom_id);
    }

    public function test_update_refreshes_the_region_from_the_selected_hierarchy(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-E', 'name' => 'Discom-East']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-E', 'name' => 'Circle East']);
        $division = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-E', 'name' => 'Division East']);

        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($admin)->put("/admin/roles/{$manager->id}", [
            'role_name' => $manager->name,
            'role' => 'relationship_manager',
            'division_id' => (string) $division->id,
        ])->assertRedirect(route('admin.roles.index'));

        $manager->refresh();

        $this->assertSame($discom->id, $manager->discom_id);
        $this->assertSame('East', $manager->region);
    }

    public function test_update_keeps_the_existing_region_when_no_hierarchy_is_selected(): void
    {
        $admin = $this->siteAdmin();
        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($admin)->put("/admin/roles/{$manager->id}", [
            'role_name' => $manager->name,
            'role' => 'relationship_manager',
        ])->assertRedirect(route('admin.roles.index'));

        $manager->refresh();

        $this->assertSame('North', $manager->region);
    }
}
