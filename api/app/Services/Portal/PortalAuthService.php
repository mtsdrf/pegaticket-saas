<?php

namespace App\Services\Portal;

use App\DTOs\Portal\RequestOtpDTO;
use App\DTOs\Portal\VerifyOtpDTO;
use App\Events\Portal\FinalCustomerRegistered;
use App\Events\Portal\PortalOtpRequested;
use App\Events\Portal\PortalOtpVerificationFailed;
use App\Events\Portal\PortalOtpVerified;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\PortalOtpDeliveryException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Mail\PortalOtpMail;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerOtp;
use App\Repositories\Contracts\FinalCustomerRepositoryInterface;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Login sem senha do cliente final (Fase 5.2 do roadmap): código de 6
 * dígitos por e-mail (OTP), sem SMS/WhatsApp (decisão já tomada — projeto
 * só tem SMTP configurado). Nunca revela se o e-mail já tinha conta.
 */
class PortalAuthService
{
    private const OTP_LENGTH = 6;
    private const EXPIRES_IN_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    /**
     * Exposto pro Controller incluir na resposta de requestOtp() sem
     * duplicar o número mágico — é uma constante fixa do sistema, não vaza
     * nada sobre o e-mail específico (não confundir com "revelar se tem
     * conta").
     */
    public static function otpExpiresInMinutes(): int
    {
        return self::EXPIRES_IN_MINUTES;
    }

    public function __construct(
        private FinalCustomerRepositoryInterface $customerRepository,
        private CustomerJWTService $jwtService,
    ) {
    }

    public function requestOtp(RequestOtpDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $customer = $this->customerRepository->findByEmail($dto->email);

            if (!$customer) {
                $customer = $this->customerRepository->create([
                    'email' => $dto->email,
                ]);

                event(new FinalCustomerRegistered(
                    finalCustomerUuid: $customer->uuid,
                    email: $customer->email,
                ));
            }

            // Invalida qualquer código anterior ainda válido — só o último
            // códiga venda pode ser usado, evita ambiguidade/confusão e
            // reduz a superfície de força bruta (um attempts counter ativo
            // por vez, não vários acumulando).
            FinalCustomerOtp::where('final_customer_id', $customer->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

            FinalCustomerOtp::create([
                'final_customer_id' => $customer->id,
                'code_hash' => hash('sha512', $code),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);

            try {
                Mail::to($customer->email)->send(new PortalOtpMail($code, self::EXPIRES_IN_MINUTES));
            } catch (TransportExceptionInterface $e) {
                throw new PortalOtpDeliveryException(__('messages.portal.otp_delivery_unavailable'), previous: $e);
            }

            event(new PortalOtpRequested(finalCustomerUuid: $customer->uuid));
        });
    }

    public function verifyOtp(VerifyOtpDTO $dto): array
    {
        // Propositalmente SEM DB::transaction envolvendo o método inteiro:
        // o incremento de `attempts` no caminho de código errado precisa
        // ser persistido mesmo quando a chamada termina lançando uma
        // exceção — DB::transaction() faz ROLLBACK de tudo (incluindo o
        // increment) sempre que a exceção escapa da closure, o que
        // zeraria silenciosamente o contador de força bruta a cada
        // tentativa errada (achado escrevendo o teste de too-many-
        // attempts). Nenhum passo aqui precisa de atomicidade multi-linha
        // de qualquer forma (cada branch só escreve numa linha).
        $customer = $this->customerRepository->findByEmail($dto->email);

        $otp = $customer
            ? FinalCustomerOtp::where('final_customer_id', $customer->id)
                ->whereNull('consumed_at')
                ->latest('id')
                ->first()
            : null;

        if (!$customer || !$otp) {
            event(new PortalOtpVerificationFailed(email: $dto->email, reason: 'not_found'));

            throw new InvalidOtpException(__('messages.portal.invalid_code'));
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            event(new PortalOtpVerificationFailed(email: $dto->email, reason: 'too_many_attempts'));

            throw new TooManyOtpAttemptsException(__('messages.portal.too_many_attempts'));
        }

        if ($otp->isExpired()) {
            event(new PortalOtpVerificationFailed(email: $dto->email, reason: 'expired'));

            throw new InvalidOtpException(__('messages.portal.expired_code'));
        }

        if (hash('sha512', $dto->code) !== $otp->code_hash) {
            $otp->increment('attempts');

            event(new PortalOtpVerificationFailed(email: $dto->email, reason: 'wrong_code'));

            throw new InvalidOtpException(__('messages.portal.invalid_code'));
        }

        $otp->update(['consumed_at' => now()]);

        event(new PortalOtpVerified(finalCustomerUuid: $customer->uuid));

        return $this->createSession($customer);
    }

    private function createSession(FinalCustomer $customer): array
    {
        return [
            'access_token' => $this->jwtService->issueAccessToken($customer),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }
}
