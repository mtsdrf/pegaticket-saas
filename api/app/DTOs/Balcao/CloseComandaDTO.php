<?php

namespace App\DTOs\Balcao;

class CloseComandaDTO
{
    /**
     * @param array<int, array{method: string, amount: float}> $payments
     */
    public function __construct(
        public readonly array $payments,
        public readonly bool $applyServiceFee,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            payments: $data['payments'],
            // Aceitar/recusar a taxa de serviço no fechamento (decisão #5). O
            // service congela na abertura e, se o tenant marcou a taxa como
            // obrigatória, força a aplicação mesmo com recusa aqui.
            applyServiceFee: array_key_exists('apply_service_fee', $data)
                ? (bool) $data['apply_service_fee']
                : true,
        );
    }
}
