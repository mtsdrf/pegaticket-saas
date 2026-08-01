<?php

namespace App\DTOs\Payment;

/**
 * Payload interno para uma cobrança PagBank (Pix ou cartão) — construído
 * pelo PagBankPaymentProvider a partir de Sale/Invoice ANTES da chamada
 * HTTP real (ainda não implementada, ver PagBankPaymentProvider). O shape
 * exato de `toArray()` (nomes de campo, aninhamento) NÃO é garantido pela
 * documentação oficial do PagBank nesta implementação — é um placeholder
 * estruturado para o dia em que a integração real for feita, não uma
 * cópia do payload real da API de Pedidos do PagBank.
 */
final class PagBankChargeRequestDTO
{
    /**
     * @param array<string, mixed> $payer
     * @param array<string, mixed> $paymentMethod
     */
    public function __construct(
        public readonly string $referenceId,
        public readonly string $amount,
        public readonly string $method,
        public readonly array $payer,
        public readonly array $paymentMethod,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            referenceId: (string) $data['reference_id'],
            amount: (string) $data['amount'],
            method: (string) $data['method'],
            payer: (array) ($data['payer'] ?? []),
            paymentMethod: (array) ($data['payment_method'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference_id' => $this->referenceId,
            'amount' => $this->amount,
            'method' => $this->method,
            'payer' => $this->payer,
            'payment_method' => $this->paymentMethod,
        ];
    }
}
