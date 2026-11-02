<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_consumer_card_header_button_and_nav_links(): void
    {
        $this->get('/')
            ->assertOk()
            // Sahyog-style tagline on the welcome card (static blade text — asserted raw).
            ->assertSee("Your Voice • Government's Responsibility", false)
            // Single consumer login card (Welcome Back), no other cards.
            ->assertSee('Welcome Back')
            ->assertSee('Consumer ID')
            ->assertSee('Send OTP')
            ->assertSee('Lodge complaints online')
            ->assertDontSee('For Regional Managers and Competent Authorities')
            // Header "Official Login" button for officials.
            ->assertSee('Official Login')
            // Nav band with the Sahyog-style important links.
            ->assertSee('Home Page')
            ->assertSee('FAQ')
            ->assertSee('RTI')
            ->assertSee('Privacy Policy')
            ->assertSee('Accessibility Statement')
            ->assertSee('Contact Us')
            // No signed-in affordances for guests.
            ->assertDontSee('My Complaints')
            ->assertDontSee('Sign out');
    }

    public function test_signed_in_consumer_is_redirected_to_the_dashboard(): void
    {
        $consumer = Consumer::factory()->create(['region' => 'North']);

        $this->actingAs($consumer, 'consumer')
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_signed_in_official_is_redirected_to_the_dashboard(): void
    {
        $manager = User::factory()->regionalManager('North')->create();

        $this->actingAs($manager)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_pending_otp_step_appears_on_the_home_page_card(): void
    {
        Consumer::factory()->create([
            'consumer_number' => 'CGRF-TEST01',
            'mobile' => '9000000001',
            'region' => 'North',
        ]);

        $this->post('/login/otp/request', ['consumer_number' => 'CGRF-TEST01'])
            ->assertRedirect(route('home'));

        $this->get('/')
            ->assertOk()
            ->assertSee('One-time password')
            ->assertSee('•••••• 0001')
            ->assertDontSee('Consumer ID');
    }
}
