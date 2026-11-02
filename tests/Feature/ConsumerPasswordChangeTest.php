<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\PortalMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsumerPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_links_consumers_to_the_password_change_page(): void
    {
        $consumer = Consumer::factory()->create();

        $this->actingAs($consumer, 'consumer')->get('/dashboard')
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee(route('password.change'), false);

        $item = PortalMenuItem::query()
            ->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)
            ->where('label', 'Change Password')
            ->sole();

        $this->assertSame('password.change', $item->route_name);
    }

    public function test_consumers_open_the_password_change_page(): void
    {
        $consumer = Consumer::factory()->create(['password' => 'stale-password']);

        $this->actingAs($consumer, 'consumer')->get('/password/change')
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('Current password');
    }

    public function test_first_time_passwordless_consumers_are_not_prompted_for_a_current_password(): void
    {
        $consumer = Consumer::factory()->create(['password' => null]);

        $this->actingAs($consumer, 'consumer')->get('/password/change')
            ->assertOk()
            ->assertSee('You have not set a password on this account yet')
            ->assertDontSee('Current password');
    }

    public function test_consumers_change_their_password_with_the_correct_current_password(): void
    {
        $consumer = Consumer::factory()->create(['password' => 'prior-secret']);

        $this->actingAs($consumer, 'consumer')->post('/password/change', [
            'current_password' => 'prior-secret',
            'password' => 'fresh-secret-01',
            'password_confirmation' => 'fresh-secret-01',
        ])->assertRedirect(route('password.change'))
            ->assertSessionHas('status', 'Password changed successfully.');

        $consumer->refresh();

        $this->assertTrue(Hash::check('fresh-secret-01', $consumer->password));
        $this->assertFalse(Hash::check('prior-secret', $consumer->password));
    }

    public function test_the_current_password_is_verified(): void
    {
        $consumer = Consumer::factory()->create(['password' => 'prior-secret']);

        $this->actingAs($consumer, 'consumer')->post('/password/change', [
            'current_password' => 'wrong-guess',
            'password' => 'fresh-secret-01',
            'password_confirmation' => 'fresh-secret-01',
        ])->assertRedirect()
            ->assertSessionHasErrors(['current_password']);

        $consumer->refresh();

        $this->assertTrue(Hash::check('prior-secret', $consumer->password));
        $this->assertFalse(Hash::check('fresh-secret-01', $consumer->password));
    }

    public function test_first_time_set_persists_a_password_without_a_current_password(): void
    {
        $consumer = Consumer::factory()->create(['password' => null]);

        $this->actingAs($consumer, 'consumer')->post('/password/change', [
            'password' => 'first-time-pass',
            'password_confirmation' => 'first-time-pass',
        ])->assertRedirect(route('password.change'))
            ->assertSessionHas('status', 'Password changed successfully.');

        $consumer->refresh();

        $this->assertTrue(Hash::check('first-time-pass', $consumer->password));
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $consumer = Consumer::factory()->create(['password' => 'same-forever']);

        $this->actingAs($consumer, 'consumer')->post('/password/change', [
            'current_password' => 'same-forever',
            'password' => 'same-forever',
            'password_confirmation' => 'same-forever',
        ])->assertSessionHasErrors(['password']);

        $consumer->refresh();

        $this->assertTrue(Hash::check('same-forever', $consumer->password));
    }
}
