<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Helper mínimo para o domínio financeiro do projeto.
 *
 * Objetivo: normalizar valores monetários em string decimal fixa e comparar
 * por centavos, evitando depender de float em regras de pagamento.
 */
class Money
{
    public static function normalize(string|int|float $value, int $scale = 2): string
    {
        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            if ($normalized === '' || ! is_numeric($normalized)) {
                throw new InvalidArgumentException('Invalid monetary amount.');
            }

            return number_format((float) $normalized, $scale, '.', '');
        }

        return number_format((float) $value, $scale, '.', '');
    }

    public static function toMinor(string|int|float $value, int $scale = 2): int
    {
        $normalized = self::normalize($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $digits = str_replace('.', '', ltrim($normalized, '-'));
        $minor = (int) $digits;

        return $negative ? -$minor : $minor;
    }

    public static function equals(string|int|float $left, string|int|float $right, int $scale = 2): bool
    {
        return self::toMinor($left, $scale) === self::toMinor($right, $scale);
    }

    /**
     * Aplica um desconto percentual sobre um valor, calculado em centavos
     * (mesma regra usada em `InvoiceService::generateForCycle`, extraída
     * aqui para não duplicar a fórmula em outros pontos que precisem do
     * mesmo cálculo, ex. `SubscriptionService::getPlanPricing`).
     *
     * @return array{gross: string, discount: string, net: string} valores normalizados
     */
    public static function applyDiscount(string|int|float $amount, string|int|float $discountPercent, int $scale = 2): array
    {
        $grossCents = self::toMinor($amount, $scale);
        $discountCents = (int) round($grossCents * ((float) $discountPercent / 100));
        $netCents = $grossCents - $discountCents;
        $divisor = 10 ** $scale;

        return [
            'gross' => self::normalize($grossCents / $divisor, $scale),
            'discount' => self::normalize($discountCents / $divisor, $scale),
            'net' => self::normalize($netCents / $divisor, $scale),
        ];
    }

    /**
     * Aplica um percentual sobre um valor em centavos com piso mínimo
     * (regra da taxa de serviço PegaTicket: `max(round(valor*percentual/100), piso)`).
     * Valor <= 0 sempre resulta em 0 (sem taxa sobre item gratuito).
     */
    public static function applyPercentWithFloor(int $amountCents, float $percentage, int $floorCents): int
    {
        if ($amountCents <= 0) {
            return 0;
        }

        return max((int) round($amountCents * $percentage / 100), $floorCents);
    }
}
