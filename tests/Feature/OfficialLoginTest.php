<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficialLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sign in as a site administrator for the mapping endpoints.
     */
    private function siteAdmin(): User
    {
        return User::factory()->siteAdmin()->create();
    }

    public function test_login_page_renders_the_username_or_email_label(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Username or Email');
    }

    public function test_mapping_created_official_can_sign_in_with_username_and_email(): void
    {
        $this->actingAs($this->siteAdmin())->post('/admin/roles', [
            'user_name' => 'Ashwin Manager',
            'role' => 'relationship_manager',
            'region' => 'North',
            'login_username' => 'ash.mgr',
            'password' => 'sign-in-secret',
        ])->assertRedirect(route('admin.roles.index'));

        $official = User::query()->where('username', 'ash.mgr')->sole();

        $this->assertSame('ashwin.manager@ht-cgrf.test', $official->email);

        // Username sign-in.
        $this->post('/login', [
            'email' => 'ash.mgr',
            'password' => 'sign-in-secret',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout');
        auth()->forgetGuards();

        // The very same official can also sign in with the generated email.
        $this->post('/login', [
            'email' => $official->email,
            'password' => 'sign-in-secret',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_official_can_sign_in_with_the_stored_email(): void
    {
        User::factory()->regionalManager('North')->create([
            'email' => 'manager@ht-cgrf.test',
            'password' => Hash::make('sign-in-secret'),
        ]);

        $this->post('/login', [
            'email' => 'manager@ht-cgrf.test',
            'password' => 'sign-in-secret',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_login_with_email_shaped_username_still_matches_email_column(): void
    {
        User::factory()->competentAuthority('North')->create([
            'email' => 'authority@ht-cgrf.test',
            'password' => Hash::make('sign-in-secret'),
        ]);

        // "authority@ht-cgrf.test" is email-shaped: looked up on email, not username.
        $this->post('/login', [
            'email' => 'authority@ht-cgrf.test',
            'password' => 'sign-in-secret',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_bad_credentials_are_rejected_for_username_and_email_lookups(): void
    {
        User::factory()->regionalManager('North')->create([
            'username' => 'rm.north',
            'password' => Hash::make('sign-in-secret'),
        ]);

        // Unknown username.
        $this->postJson('/login', [
            'email' => 'nobody.here',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The provided credentials do not match our records.');

        // Known username, wrong password.
        $this->postJson('/login', [
            'email' => 'rm.north',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The provided credentials do not match our records.');
    }
}
