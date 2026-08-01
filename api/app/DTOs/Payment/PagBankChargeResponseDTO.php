<?php

namespace App\DTOs\Payment;

/**
 * Retorno normalizado de uma cobrança PagBank — o que
 * PagBankPaymentProvider::createOrder() produziria a partir da resposta
 * HTTP real do PagBank (ainda não implementada). Os campos abaixo cobrem o
 * mínimo que o domínio (SalePaymentService/Payment) precisa para persistir
 * a cobrança local; nomes/estrutura exatos da resposta real do PagBank
 * ainda não foram validados contra a API (nenhuma credencial disponível
 * nesta sessão) — ajustar quando a integração real acontecer.
 */
final class PagBankChargeResponseDTO
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $providerChargeId,
        public readonly string $status,
        public readonly string $method,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerChargeId: (string) ($data['id'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            method: (string) ($data['method'] ?? ''),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->providerChargeId,
            'status' => $this->status,
            'method' => $this->method,
            'metadata' => $this->metadata,
        ];
    }
}
