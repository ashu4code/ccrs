<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsumerOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CONSUMER_NUMBER = 'CGRF-TEST01';

    private const TEST_MOBILE = '9000000001';

    /**
     * Create a consumer fixture with a fixed consumer number and mobile.
     */
    private function consumer(): Consumer
    {
        return Consumer::factory()->create([
            'consumer_number' => self::TEST_CONSUMER_NUMBER,
            'mobile' => self::TEST_MOBILE,
            'region' => 'North',
        ]);
    }

    /**
     * Request an OTP through the browser route and grab the dev-mode code.
     * The request redirects back to the home page, where the consumer card
     * advances to the verification step.
     */
    private function requestOtpCode(): string
    {
        $this->post('/login/otp/request', [
            'consumer_number' => self::TEST_CONSUMER_NUMBER,
        ])->assertRedirect(route('home'));

        $code = (string) $this->sessionValue('otp.dev_code');

        $this->assertSame(6, strlen($code), 'Expected a 6-digit dev OTP code.');

        return $code;
    }

    /**
     * Read a value from the test session store (same store the requests use).
     */
    private function sessionValue(string $key): mixed
    {
        return $this->app['session.store']->get($key);
    }

    /**
     * A six-digit code guaranteed not to match the real one.
     */
    private function wrongCode(string $realCode): string
    {
        return $realCode === '000000' ? '000001' : '000000';
    }

    public function test_official_login_page_renders_only_the_official_card(): void
    {
        // `/login` is the official-only page, Sahyog LoginAdm-style; consumers
        // sign in on the home page card instead.
        $this->get('/login')
            ->assertOk()
            ->assertSee('Official Login')
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertSee('Relationship Managers and Competent Authorities')
            ->assertDontSee('Consumer ID')
            ->assertDontSee('Send OTP')
            ->assertDontSee('Welcome Back');
    }

    public function test_consumer_requests_otp_and_receives_dev_code(): void
    {
        $this->consumer();

        $code = $this->requestOtpCode();

        $this->assertDatabaseCount('otps', 1);

        $otp = Otp::query()->sole();
        $this->assertNull($otp->consumed_at);
        $this->assertNotNull($otp->expires_at);
        $this->assertTrue($otp->expires_at->isFuture());
        $this->assertSame(0, $otp->attempts);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
    }

    public function test_otp_step_shows_masked_mobile_and_dev_code(): void
    {
        $this->consumer();
        $this->requestOtpCode();

        $this->get('/')
            ->assertOk()
            ->assertSee('•••••• 0001')
            ->assertSee('one-time password was sent', false)
            ->assertSee((string) $this->sessionValue('otp.dev_code'))
            // Markup invariants the OTP entry script depends on: the segmented
            // group container and the hidden carrier field the JS syncs the
            // joined digits into (checked raw — no client JS in feature tests).
            ->assertSee('data-otp-group', false)
            ->assertSee('<input type="hidden" name="code"', false);
    }

    public function test_wrong_code_keeps_the_otp_step_and_dev_code_on_screen(): void
    {
        $this->consumer();
        $code = $this->requestOtpCode();

        $this->post('/login/otp/verify', ['code' => $this->wrongCode($code)])
            ->assertStatus(302);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('One-time password')
            ->assertSee('The code is incorrect. Check the SMS and try again.')
            ->assertSee('•••••• 0001')
            ->assertSee((string) $this->sessionValue('otp.dev_code'));

        // The dev code stays visible across further loads until the sign-in
        // succeeds or the step is reset (it is no longer flash data).

        $this->get(route('home'))
            ->assertOk()
            ->assertSee((string) $this->sessionValue('otp.dev_code'));
    }

    public function test_exhausted_otp_returns_to_the_consumer_id_step_with_a_reason(): void
    {
        $this->consumer();
        $code = $this->requestOtpCode();
        $wrong = $this->wrongCode($code);

        foreach (range(1, 3) as $attempt) {
            $this->post('/login/otp/verify', ['code' => $wrong]);
        }

        // The next attempt finds the OTP fully attempted and clears the pending step.
        $this->post('/login/otp/verify', ['code' => $wrong]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Consumer ID')
            ->assertSee('Send OTP')
            ->assertSee('This code is expired or fully attempted. Request a new one.')
            ->assertDontSee('One-time password');
    }

    public function test_wrong_code_is_rejected_and_counts_an_attempt(): void
    {
        $this->consumer();
        $code = $this->requestOtpCode();

        $this->postJson('/login/otp/verify', ['code' => $this->wrongCode($code)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The code is incorrect. Check the SMS and try again.');

        $this->assertGuest('consumer');
        $this->assertSame(1, Otp::query()->sole()->attempts);
    }

    public function test_correct_code_signs_consumer_in_and_lands_on_the_dashboard(): void
    {
        $consumer = $this->consumer();
        $code = $this->requestOtpCode();

        $this->post('/login/otp/verify', ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($consumer, 'consumer');
        $this->assertNull($this->sessionValue('auth.password_setup_pending'));
    }

    public function test_first_time_password_set_now_happens_from_the_change_password_page(): void
    {
        $consumer = $this->consumer();
        $code = $this->requestOtpCode();

        $this->post('/login/otp/verify', ['code' => $code])->assertRedirect(route('dashboard'));

        // The new password is validated the same way from the sidebar page.
        $this->postJson('/password/change', [
            'password' => 'short12',
            'password_confirmation' => 'short12',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->post('/password/change', [
            'password' => 'grievance-pass-123',
            'password_confirmation' => 'grievance-pass-123',
        ])->assertRedirect(route('password.change'))
            ->assertSessionHas('status', 'Password changed successfully.');

        $consumer->refresh();
        $this->assertTrue(Hash::check('grievance-pass-123', $consumer->password));
        $this->assertAuthenticatedAs($consumer, 'consumer');
    }

    public function test_officials_cannot_open_the_consumer_password_change_page(): void
    {
        // `/password/change` sits behind the `consumer` guard, so official
        // sessions never reach the controller.
        $official = User::factory()->regionalManager('North')->create();

        $this->actingAs($official)->get(route('password.change'))->assertRedirect(route('login'));

        $this->actingAs($official)
            ->postJson('/password/change', [
                'password' => 'anything-at-all',
                'password_confirmation' => 'anything-at-all',
            ])
            ->assertUnauthorized();
    }

    public function test_three_wrong_codes_exhaust_the_otp(): void
    {
        $this->consumer();
        $code = $this->requestOtpCode();
        $wrong = $this->wrongCode($code);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/login/otp/verify', ['code' => $wrong])
                ->assertStatus(422)
                ->assertJsonPath('message', 'The code is incorrect. Check the SMS and try again.');
        }

        // Even the correct code is now rejected: the OTP is fully attempted.
        $this->postJson('/login/otp/verify', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This code is expired or fully attempted. Request a new one.');

        $this->assertGuest('consumer');
    }

    public function test_otp_requests_are_rate_limited_to_two_per_minute(): void
    {
        $this->consumer();

        $this->post('/login/otp/request', ['consumer_number' => self::TEST_CONSUMER_NUMBER])
            ->assertRedirect(route('home'));

        $this->post('/login/otp/request', ['consumer_number' => self::TEST_CONSUMER_NUMBER])
            ->assertRedirect(route('home'));

        $this->postJson('/login/otp/request', ['consumer_number' => self::TEST_CONSUMER_NUMBER])
            ->assertStatus(429);

        $this->assertDatabaseCount('otps', 2);
    }

    public function test_otp_request_requires_a_known_consumer_number(): void
    {
        // Missing field entirely.
        $this->postJson('/login/otp/request', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consumer_number']);

        // Unknown consumer number.
        $this->postJson('/login/otp/request', ['consumer_number' => 'CGRF-UNKNOWN'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No registered consumer matches these details.');

        $this->assertDatabaseCount('otps', 0);
    }

    public function test_consumer_with_existing_password_goes_straight_to_the_dashboard(): void
    {
        $consumer = Consumer::factory()->create([
            'consumer_number' => self::TEST_CONSUMER_NUMBER,
            'mobile' => self::TEST_MOBILE,
            'region' => 'North',
            'password' => Hash::make('existing-pass-123'),
        ]);

        $code = $this->requestOtpCode();

        $this->post('/login/otp/verify', ['code' => $code])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($consumer, 'consumer');
        $this->assertNull($this->sessionValue('auth.password_setup_pending'));
    }

    public function test_forget_query_param_resets_the_otp_step(): void
    {
        $this->consumer();
        $this->requestOtpCode();

        $this->get('/?forget=1')
            ->assertOk()
            ->assertSee('Consumer ID')
            ->assertSessionMissing('otp.id')
            ->assertSessionMissing('otp.masked_mobile')
            ->assertSessionMissing('otp.dev_code');
    }

    public function test_official_email_login_still_works(): void
    {
        $manager = User::factory()->regionalManager('North')->create();

        // Wrong credentials first (still a guest — the guest middleware would
        // redirect repeat attempts by an already-authenticated session).
        $this->postJson('/login', ['email' => $manager->email, 'password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson('/login', ['email' => $manager->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'relationship_manager');

        $this->assertAuthenticatedAs($manager);
    }
}
