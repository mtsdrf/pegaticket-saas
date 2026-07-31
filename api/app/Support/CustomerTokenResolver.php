<?php

namespace App\Support;

use App\Exceptions\CustomerTokenBlacklistedException;
use App\Exceptions\FinalCustomerNotFoundException;
use App\Exceptions\InvalidCustomerTokenException;
use App\Models\Auth\TokenBlacklist;
use App\Models\FinalCustomer\FinalCustomer;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Resolução do Bearer token de um FinalCustomer (parseToken → subject model
 * → jti → blacklist → find), compartilhada entre CustomerJwtAccessMiddleware
 * (exige, 401 em qualquer falha) e OptionalCustomerJwtMiddleware (nunca
 * exige, qualquer exceção vira "segue anônimo"). Exceções de
 * JWTAuth (TokenExpiredException/TokenInvalidException/JWTException) não são
 * capturadas aqui — propagam pra quem chamar decidir.
 */
class CustomerTokenResolver
{
    public static function resolve(): FinalCustomer
    {
        $token = JWTAuth::parseToken();
        $payload = $token->getPayload();

        if (!$token->checkSubjectModel(FinalCustomer::class)) {
            throw new InvalidCustomerTokenException();
        }

        $jti = (string) $payload->get('jti');

        if (!$jti) {
            throw new InvalidCustomerTokenException();
        }

        $blacklisted = TokenBlacklist::where('jti', $jti)
            ->where('expires_at', '>', now())
            ->exists();

        if ($blacklisted) {
            throw new CustomerTokenBlacklistedException();
        }

        $customer = FinalCustomer::find($payload->get('sub'));

        if (!$customer) {
            throw new FinalCustomerNotFoundException();
        }

        return $customer;
    }
}
