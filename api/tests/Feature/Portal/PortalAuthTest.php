<?php

namespace Tests\Feature\Portal;

use App\Mail\PortalOtpMail;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Login sem senha do cliente final via OTP por e-mail (roadmap 5.2).
 */
class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private function requestOtpAndCaptureCode(string $email): string
    {
        Mail::fake();

        $this->postJson('/api/v1/portal/auth/request-otp', ['email' => $email])
            ->assertStatus(200);

        $code = null;

        Mail::assertSent(PortalOtpMail::class, function ($mail) use ($email, &$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    #[Test]
    public function request_otp_creates_a_final_customer_and_always_returns_generic_success(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/portal/auth/request-otp', [
            'email' => 'novo-cliente@test.com',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('final_customers', [
            'email' => 'novo-cliente@test.com',
        ]);

        Mail::assertSent(PortalOtpMail::class);
    }

    #[Test]
    public function request_otp_does_not_reveal_whether_the_email_already_had_an_account(): void
    {
        Mail::fake();

        FinalCustomer::create(['email' => 'ja-existe@test.com']);

        $existing = $this->postJson('/api/v1/portal/auth/request-otp', [
            'email' => 'ja-existe@test.com',
        ]);

        $new = $this->postJson('/api/v1/portal/auth/request-otp', [
            'email' => 'nao-existe@test.com',
        ]);

        $this->assertSame($existing->json('message'), $new->json('message'));
        $existing->assertStatus(200);
        $new->assertStatus(200);
    }

    #[Test]
    public function verify_otp_with_correct_code_authenticates_and_issues_a_valid_token(): void
    {
        $code = $this->requestOtpAndCaptureCode('cliente@test.com');

        $response = $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente@test.com',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);

        $token = $response->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'cliente@test.com');
    }

    #[Test]
    public function verify_otp_rejects_wrong_code(): void
    {
        $this->requestOtpAndCaptureCode('cliente2@test.com');

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente2@test.com',
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    #[Test]
    public function verify_otp_rejects_expired_code(): void
    {
        $code = $this->requestOtpAndCaptureCode('cliente3@test.com');

        FinalCustomerOtp::query()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente3@test.com',
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    #[Test]
    public function verify_otp_rejects_already_consumed_code(): void
    {
        $code = $this->requestOtpAndCaptureCode('cliente4@test.com');

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente4@test.com',
            'code' => $code,
        ])->assertStatus(200);

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente4@test.com',
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    #[Test]
    public function verify_otp_blocks_after_too_many_wrong_attempts(): void
    {
        $this->requestOtpAndCaptureCode('cliente5@test.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/portal/auth/verify-otp', [
                'email' => 'cliente5@test.com',
                'code' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente5@test.com',
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
    }

    #[Test]
    public function requesting_a_new_code_invalidates_the_previous_one(): void
    {
        $firstCode = $this->requestOtpAndCaptureCode('cliente6@test.com');
        $secondCode = $this->requestOtpAndCaptureCode('cliente6@test.com');

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente6@test.com',
            'code' => $firstCode,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');

        $this->postJson('/api/v1/portal/auth/verify-otp', [
            'email' => 'cliente6@test.com',
            'code' => $secondCode,
        ])->assertStatus(200);
    }
}
