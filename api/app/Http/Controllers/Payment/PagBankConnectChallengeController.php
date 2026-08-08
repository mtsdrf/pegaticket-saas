<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use Illuminate\Http\JsonResponse;

/**
 * Rota pública exigida pelo Connect Challenge do PagBank
 * (developer.pagbank.com.br/docs/connect-challenge) — pré-requisito para
 * cadastrar a aplicação Connect e obter client_id/client_secret (roadmap
 * fase R2.7). O PagBank busca esta URL para validar a chave pública antes
 * de aprovar o cadastro em Sandbox (SLA de até 2 dias úteis).
 *
 * Serve só a chave PÚBLICA, derivada em runtime da privada (nunca
 * armazenada em texto separado, reduz superfície de exposição acidental).
 * A privada nunca é lida por esta rota nem exposta em nenhuma resposta.
 */
class PagBankConnectChallengeController extends Controller
{
    public function publicKey(): JsonResponse
    {
        $privateKeyPath = (string) config('services.pagbank.connect_challenge_private_key_path');

        if ($privateKeyPath === '' || ! is_readable($privateKeyPath)) {
            return APIResponse::error(
                __('messages.pagbank_connect.challenge_key_unavailable'),
                503,
                'PAGBANK_CONNECT_CHALLENGE_KEY_UNAVAILABLE'
            );
        }

        $privateKeyPem = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyPem === false ? '' : $privateKeyPem);

        if ($privateKey === false) {
            return APIResponse::error(
                __('messages.pagbank_connect.challenge_key_unavailable'),
                503,
                'PAGBANK_CONNECT_CHALLENGE_KEY_UNAVAILABLE'
            );
        }

        $details = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $details['key'] ?? null;

        if (! is_string($publicKeyPem) || $publicKeyPem === '') {
            return APIResponse::error(
                __('messages.pagbank_connect.challenge_key_unavailable'),
                503,
                'PAGBANK_CONNECT_CHALLENGE_KEY_UNAVAILABLE'
            );
        }

        return response()->json([
            'public_key' => $publicKeyPem,
            'created_at' => filemtime($privateKeyPath) ?: time(),
        ]);
    }
}
