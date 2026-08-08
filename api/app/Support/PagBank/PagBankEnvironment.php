<?php

namespace App\Support\PagBank;

/**
 * Resolução de ambiente/base URL compartilhada entre os adapters PagBank
 * (PagBankPaymentProvider — rail de venda — e PagBankConnectService —
 * fluxo OAuth Connect). Único ponto de verdade para as constantes de
 * BASE_URL, evitando duplicar os hosts sandbox/produção em cada classe.
 */
class PagBankEnvironment
{
    public const BASE_URL_SANDBOX = 'https://sandbox.api.pagseguro.com';

    public const BASE_URL_PRODUCTION = 'https://api.pagseguro.com';

    public const CONNECT_AUTHORIZE_URL_SANDBOX = 'https://connect.sandbox.pagbank.com.br/oauth2/authorize';

    public const CONNECT_AUTHORIZE_URL_PRODUCTION = 'https://connect.pagbank.com.br/oauth2/authorize';

    public static function resolveApiBaseUrl(string $environment): string
    {
        return $environment === 'production' ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
    }

    public static function resolveConnectAuthorizeUrl(string $environment): string
    {
        return $environment === 'production'
            ? self::CONNECT_AUTHORIZE_URL_PRODUCTION
            : self::CONNECT_AUTHORIZE_URL_SANDBOX;
    }
}
