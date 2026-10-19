<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Notifications\Auth\AccountAlreadyExistsNotification;
use App\Notifications\Auth\RegistrationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'nimal@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_register_emails_a_code_and_creates_no_user_yet(): void
    {
        $this->register()
            ->assertStatus(202)
            ->assertJson(['message' => 'Please check your email to continue your registration.']);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->codeSentTo(self::EMAIL));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_correct_code_creates_a_verified_user(): void
    {
        $this->register();

        $response = $this->verify(self::EMAIL, $this->codeSentTo(self::EMAIL))
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Nimal')
            ->assertJsonPath('data.last_name', 'Perera')
            ->assertJsonPath('data.email', self::EMAIL)
            ->assertJsonPath('message', 'Your account has been created. Please sign in.')
            ->assertJsonMissingPath('data.password');

        // Verifying does not sign the user in; the app goes to the sign-in screen.
        $this->assertArrayNotHasKey('access_token', $response->json('data'));

        $user = User::sole();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('Secret123', $user->password);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->register();
        $code = $this->codeSentTo(self::EMAIL);

        $this->verify(self::EMAIL, $this->differentCode($code))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CODE')
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_code_stops_working_after_five_wrong_attempts(): void
    {
        $this->register();
        $code = $this->codeSentTo(self::EMAIL);

        for ($i = 0; $i < 5; $i++) {
            $this->verify(self::EMAIL, $this->differentCode($code))->assertUnprocessable();
        }

        $this->verify(self::EMAIL, $code)->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->register();
        $code = $this->codeSentTo(self::EMAIL);

        $this->travel(11)->minutes();

        $this->verify(self::EMAIL, $code)->assertUnprocessable();
    }

    public function test_code_works_only_once(): void
    {
        $this->register();
        $code = $this->codeSentTo(self::EMAIL);

        $this->verify(self::EMAIL, $code)->assertCreated();
        $this->verify(self::EMAIL, $code)->assertUnprocessable();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_code_for_one_email_does_not_verify_another(): void
    {
        $this->register(['email' => 'kamala@example.com']);
        $this->register();

        $this->verify(self::EMAIL, $this->codeSentTo('kamala@example.com'))->assertUnprocessable();
    }

    public function test_code_must_be_six_digits_sent_as_text(): void
    {
        $this->register();

        $this->postJson('/api/v1/auth/register/verify', ['email' => self::EMAIL, 'code' => 'abc'])
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/register/verify', ['email' => self::EMAIL, 'code' => 123456])
            ->assertJsonValidationErrors('code');
    }

    public function test_resend_too_soon_is_refused(): void
    {
        $this->register();

        $this->resend(self::EMAIL)
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_CODE_REQUESTS');
    }

    public function test_resend_sends_a_new_code_and_the_old_one_stops_working(): void
    {
        $this->register();
        $oldCode = $this->codeSentTo(self::EMAIL);

        $this->travel(61)->seconds();
        $this->resend(self::EMAIL)->assertStatus(202);

        Notification::assertSentOnDemandTimes(RegistrationCodeNotification::class, 2);
        $newCode = $this->codeSentTo(self::EMAIL);

        if ($newCode !== $oldCode) {
            $this->verify(self::EMAIL, $oldCode)->assertUnprocessable();
        }

        $this->verify(self::EMAIL, $newCode)->assertCreated();
    }

    public function test_resend_for_an_unknown_email_gives_the_same_reply_and_sends_nothing(): void
    {
        $this->resend('nobody@example.com')
            ->assertStatus(202)
            ->assertJson(['message' => 'Please check your email to continue your registration.']);

        Notification::assertNothingSent();
    }

    public function test_registering_again_replaces_the_pending_registration(): void
    {
        $this->register();

        $this->travel(61)->seconds();
        $this->register(['first_name' => 'Sunil']);

        $this->verify(self::EMAIL, $this->codeSentTo(self::EMAIL))->assertCreated();

        $this->assertSame('Sunil', User::sole()->first_name);
    }

    public function test_existing_email_gets_the_same_reply_and_a_warning_instead_of_a_code(): void
    {
        $existing = User::factory()->create(['email' => self::EMAIL, 'first_name' => 'Original']);

        $this->register()
            ->assertStatus(202)
            ->assertJson(['message' => 'Please check your email to continue your registration.']);

        Notification::assertSentOnDemand(AccountAlreadyExistsNotification::class);
        Notification::assertSentOnDemandTimes(RegistrationCodeNotification::class, 0);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Original', $existing->fresh()->first_name);
    }

    public function test_email_is_stored_in_lowercase(): void
    {
        $this->register(['email' => 'Nimal@Example.COM']);

        $this->verify('NIMAL@example.com', $this->codeSentTo(self::EMAIL))->assertCreated();

        $this->assertSame(self::EMAIL, User::sole()->email);
    }

    public function test_invalid_input_is_rejected_with_errors_per_field(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => '',
            'last_name' => str_repeat('a', 101),
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);

        Notification::assertNothingSent();
    }

    public function test_password_needs_letters_and_numbers(): void
    {
        $this->register(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertJsonValidationErrors('password');

        $this->register(['password' => '12345678', 'password_confirmation' => '12345678'])
            ->assertJsonValidationErrors('password');
    }

    public function test_too_many_registrations_for_one_email_are_refused(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->register()->assertStatus(202);
            $this->travel(61)->seconds();
        }

        $this->register()->assertStatus(429);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/register', array_merge([
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'email' => self::EMAIL,
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides));
    }

    private function verify(string $email, string $code): TestResponse
    {
        return $this->postJson('/api/v1/auth/register/verify', ['email' => $email, 'code' => $code]);
    }

    private function resend(string $email): TestResponse
    {
        return $this->postJson('/api/v1/auth/register/resend', ['email' => $email]);
    }

    /**
     * The code from the most recent registration email sent to this address.
     *
     * Codes are stored only as hashes, so tests read them from the email.
     */
    private function codeSentTo(string $email): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            RegistrationCodeNotification::class,
            function (RegistrationCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code) {
                if ($notifiable->routes['mail'] !== $email) {
                    return false;
                }

                $code = $notification->code;

                return true;
            }
        );

        return $code;
    }

    private function differentCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
