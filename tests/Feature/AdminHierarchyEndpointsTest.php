<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Discom;
use App\Models\Division;
use App\Models\Section;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHierarchyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Act as a signed-in site administrator.
     */
    private function siteAdmin(): User
    {
        return User::factory()->siteAdmin()->create();
    }

    public function test_circles_are_scoped_to_the_selected_discom(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);
        $otherDiscom = Discom::query()->create(['code' => 'DISCOM-S', 'name' => 'Discom-South']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-N', 'name' => 'Circle Gaya']);
        Circle::query()->create(['discom_id' => $otherDiscom->id, 'code' => 'CIRCLE-S', 'name' => 'Circle Patna']);
        Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-X', 'name' => 'Inactive Circle', 'is_active' => false]);

        $this->actingAs($admin)
            ->getJson("/admin/hierarchy/circles?discom_id={$discom->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $circle->id)
            ->assertJsonPath('data.0.name', 'Circle Gaya');
    }

    public function test_divisions_are_scoped_to_the_selected_circle(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-N', 'name' => 'Circle Gaya']);
        $otherCircle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-M', 'name' => 'Circle Muzaffarpur']);
        $division = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-N', 'name' => 'Division Gaya(R)']);
        Division::query()->create(['circle_id' => $otherCircle->id, 'code' => 'DIV-M', 'name' => 'Division Muzaffarpur']);

        $this->actingAs($admin)
            ->getJson("/admin/hierarchy/divisions?circle_id={$circle->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $division->id);
    }

    public function test_subdivisions_are_scoped_to_the_selected_division(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-N', 'name' => 'Circle Gaya']);
        $division = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-N', 'name' => 'Division Gaya(R)']);
        $otherDivision = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-P', 'name' => 'Division Patna']);
        $subdivision = Subdivision::query()->create(['division_id' => $division->id, 'code' => 'SUB-N', 'name' => 'Subdivision Tekari']);
        Subdivision::query()->create(['division_id' => $otherDivision->id, 'code' => 'SUB-P', 'name' => 'Subdivision Patna']);

        $this->actingAs($admin)
            ->getJson("/admin/hierarchy/subdivisions?division_id={$division->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $subdivision->id);
    }

    public function test_sections_are_scoped_to_the_selected_subdivision(): void
    {
        $admin = $this->siteAdmin();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);
        $circle = Circle::query()->create(['discom_id' => $discom->id, 'code' => 'CIRCLE-N', 'name' => 'Circle Gaya']);
        $division = Division::query()->create(['circle_id' => $circle->id, 'code' => 'DIV-N', 'name' => 'Division Gaya(R)']);
        $subdivision = Subdivision::query()->create(['division_id' => $division->id, 'code' => 'SUB-N', 'name' => 'Subdivision Tekari']);
        $otherSubdivision = Subdivision::query()->create(['division_id' => $division->id, 'code' => 'SUB-K', 'name' => 'Subdivision Konch']);
        $section = Section::query()->create(['subdivision_id' => $subdivision->id, 'code' => 'SEC-T', 'name' => 'Section Konch']);
        Section::query()->create(['subdivision_id' => $otherSubdivision->id, 'code' => 'SEC-K', 'name' => 'Section Bodh Gaya']);

        $this->actingAs($admin)
            ->getJson("/admin/hierarchy/sections?subdivision_id={$subdivision->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $section->id);
    }

    public function test_circles_require_a_valid_discom_id(): void
    {
        $admin = $this->siteAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/hierarchy/circles')
            ->assertStatus(422);

        $this->actingAs($admin)
            ->getJson('/admin/hierarchy/circles?discom_id=9999')
            ->assertStatus(422);
    }

    public function test_officials_without_the_site_admin_role_cannot_read_hierarchy(): void
    {
        $manager = User::factory()->regionalManager('North')->create();
        $discom = Discom::query()->create(['code' => 'DISCOM-N', 'name' => 'Discom-North']);

        $this->actingAs($manager)
            ->getJson("/admin/hierarchy/circles?discom_id={$discom->id}")
            ->assertForbidden();
    }
}
