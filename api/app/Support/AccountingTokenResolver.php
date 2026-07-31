<?php

namespace App\Support;

use App\Exceptions\AccountingOfficeNotFoundException;
use App\Exceptions\CustomerTokenBlacklistedException;
use App\Exceptions\InvalidAccountingTokenException;
use App\Models\Accounting\AccountingOffice;
use App\Models\Auth\TokenBlacklist;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Resolução do Bearer token de um AccountingOffice (parseToken → subject model
 * → jti → blacklist → find), réplica deliberada de CustomerTokenResolver para
 * a identidade do contador. Exceções de JWTAuth propagam pra quem chamar.
 */
class AccountingTokenResolver
{
    public static function resolve(): AccountingOffice
    {
        $token = JWTAuth::parseToken();
        $payload = $token->getPayload();

        if (!$token->checkSubjectModel(AccountingOffice::class)) {
            throw new InvalidAccountingTokenException();
        }

        $jti = (string) $payload->get('jti');

        if (!$jti) {
            throw new InvalidAccountingTokenException();
        }

        $blacklisted = TokenBlacklist::where('jti', $jti)
            ->where('expires_at', '>', now())
            ->exists();

        if ($blacklisted) {
            throw new CustomerTokenBlacklistedException();
        }

        $office = AccountingOffice::find($payload->get('sub'));

        if (!$office) {
            throw new AccountingOfficeNotFoundException();
        }

        return $office;
    }
}
