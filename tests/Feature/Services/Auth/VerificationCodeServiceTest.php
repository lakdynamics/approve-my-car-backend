<?php

namespace Tests\Feature\Services\Auth;

use App\Enums\VerificationCodePurpose;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Models\EmailVerificationCode;
use App\Services\Auth\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerificationCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'nimal@example.com';

    private VerificationCodeService $codes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = $this->app->make(VerificationCodeService::class);
    }

    public function test_issued_code_has_six_digits_and_is_stored_only_as_a_hash(): void
    {
        $code = $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $stored = EmailVerificationCode::sole();
        $this->assertNotSame($code, $stored->code_hash);
        $this->assertSame(64, strlen($stored->code_hash));
    }

    public function test_payload_is_encrypted_in_the_database(): void
    {
        $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register, ['first_name' => 'Nimal']);

        $raw = DB::table('email_verification_codes')->value('payload');

        $this->assertStringNotContainsString('Nimal', $raw);
        $this->assertSame(['first_name' => 'Nimal'], EmailVerificationCode::sole()->payload);
    }

    public function test_correct_code_verifies_once_only(): void
    {
        $code = $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);

        $record = $this->codes->verify(self::EMAIL, VerificationCodePurpose::Register, $code);
        $this->assertSame(self::EMAIL, $record->email);

        $this->expectException(InvalidVerificationCodeException::class);
        $this->codes->verify(self::EMAIL, VerificationCodePurpose::Register, $code);
    }

    public function test_wrong_code_adds_an_attempt(): void
    {
        $code = $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);

        try {
            $this->codes->verify(self::EMAIL, VerificationCodePurpose::Register, $code === '000000' ? '111111' : '000000');
            $this->fail('A wrong code was accepted.');
        } catch (InvalidVerificationCodeException) {
            $this->assertSame(1, EmailVerificationCode::sole()->attempts);
        }
    }

    public function test_new_code_replaces_the_previous_one(): void
    {
        $first = $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);
        $second = $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);

        if ($first !== $second) {
            try {
                $this->codes->verify(self::EMAIL, VerificationCodePurpose::Register, $first);
                $this->fail('A replaced code was accepted.');
            } catch (InvalidVerificationCodeException) {
                // Expected.
            }
        }

        $this->codes->verify(self::EMAIL, VerificationCodePurpose::Register, $second);
        $this->assertSame(2, EmailVerificationCode::whereNotNull('consumed_at')->count());
    }

    public function test_expired_codes_are_pruned_a_day_after_expiry(): void
    {
        $this->codes->issue(self::EMAIL, VerificationCodePurpose::Register);

        $this->travel(1)->days();
        $this->travel(11)->minutes();
        $this->artisan('model:prune', ['--model' => EmailVerificationCode::class]);

        $this->assertDatabaseCount('email_verification_codes', 0);
    }
}
