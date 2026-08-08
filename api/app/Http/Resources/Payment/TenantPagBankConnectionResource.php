<?php

namespace App\Http\Resources\Payment;

use App\Support\BrazilDocument;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nunca expõe access_token_encrypted/refresh_token_encrypted/
 * document_encrypted/connect_state — só o estado necessário pro
 * frontend exibir status da conexão. `document_masked` (R2.2) mostra só
 * os últimos dígitos do CPF/CNPJ, nunca o documento completo.
 */
class TenantPagBankConnectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'connection_type' => $this->connection_type,
            'account_person_type' => $this->account_person_type,
            'status' => $this->status,
            'provider_status' => $this->provider_status,
            'account_id' => $this->account_id,
            'document_masked' => $this->maskDocument(),
            'environment' => $this->environment,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'disconnected_at' => $this->disconnected_at?->toIso8601String(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
        ];
    }

    private function maskDocument(): ?string
    {
        // document_encrypted é 'encrypted' no Model — o valor já vem
        // descriptografado aqui (Eloquent cast), só dígitos, nunca é
        // devolvido inteiro na resposta.
        $document = (string) ($this->document_encrypted ?? '');

        if ($document === '') {
            return null;
        }

        $length = strlen($document);
        $visible = min(2, $length);
        $masked = str_repeat('*', max($length - $visible, 0)).substr($document, -$visible);

        return $length === 11
            ? BrazilDocument::normalizeCpf($masked) !== null ? $this->formatMaskedCpf($masked) : $masked
            : $this->formatMaskedCnpj($masked, $length);
    }

    private function formatMaskedCpf(string $masked): string
    {
        // ***.***.**9-00 (formato CPF, dígitos mascarados exceto os 2
        // finais já isolados em maskDocument()).
        return sprintf(
            '%s.%s.%s-%s',
            substr($masked, 0, 3),
            substr($masked, 3, 3),
            substr($masked, 6, 3),
            substr($masked, 9, 2)
        );
    }

    private function formatMaskedCnpj(string $masked, int $length): string
    {
        if ($length !== 14) {
            return $masked;
        }

        // **.***.***/****-** (formato CNPJ).
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($masked, 0, 2),
            substr($masked, 2, 3),
            substr($masked, 5, 3),
            substr($masked, 8, 4),
            substr($masked, 12, 2)
        );
    }
}
