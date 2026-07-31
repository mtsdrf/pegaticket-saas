<?php

namespace App\Support;

class BrazilDocument
{
    public const CNPJ_INPUT_PATTERN = '/^(?:[A-Z0-9]{12}[0-9]{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-[0-9]{2})$/i';

    public const CPF_OR_CNPJ_INPUT_PATTERN = '/^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2}|[A-Z0-9]{12}[0-9]{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-[0-9]{2})$/i';

    public static function normalizeCnpj(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return $normalized !== '' ? $normalized : null;
    }

    public static function normalizeCpf(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) preg_replace('/\D/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    public static function normalizeCpfOrCnpj(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/[A-Za-z]/', $value) === 1) {
            return self::normalizeCnpj($value);
        }

        $digits = self::normalizeCpf($value);
        if ($digits !== null && strlen($digits) === 11) {
            return $digits;
        }

        return self::normalizeCnpj($value);
    }
}
