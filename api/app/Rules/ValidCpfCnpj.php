<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida dígito verificador real de CPF (11) ou CNPJ (14), a partir do
 * valor já normalizado (só dígitos) via BrazilDocument::normalizeCpf/Cnpj.
 * Reaproveitado por CreatePagBankSellerAccountRequest (R2.2) — nenhum
 * validador de checksum existia no projeto antes disso.
 */
class ValidCpfCnpj implements ValidationRule
{
    public function __construct(private readonly string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        $valid = match ($this->type) {
            'cpf' => self::isValidCpf($digits),
            'cnpj' => self::isValidCnpj($digits),
            default => false,
        };

        if (! $valid) {
            $fail(__('messages.pagbank_account.invalid_document'));
        }
    }

    public static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $calc = function (string $base, array $weights): int {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (int) $base[$i] * $weight;
            }
            $mod = $sum % 11;

            return $mod < 2 ? 0 : 11 - $mod;
        };

        $digit1 = $calc(substr($cnpj, 0, 12), $weights1);
        if ($digit1 !== (int) $cnpj[12]) {
            return false;
        }

        $digit2 = $calc(substr($cnpj, 0, 13), $weights2);

        return $digit2 === (int) $cnpj[13];
    }
}
