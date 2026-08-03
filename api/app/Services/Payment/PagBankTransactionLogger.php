<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

class PagBankTransactionLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::channel('pagbank_transactions')->info($event, $this->sanitize($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::channel('pagbank_transactions')->error($event, $this->sanitize($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::channel('pagbank_transactions')->warning($event, $this->sanitize($context));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $sensitivePaths = [
            ['request_body', 'charges', 0, 'payment_method', 'card', 'encrypted'],
            ['request_body', 'charges', 0, 'payment_method', 'card', 'security_code'],
            ['request_body', 'charges', 0, 'payment_method', 'holder', 'tax_id'],
            ['request_body', 'customer', 'tax_id'],
            ['request_body', 'customer', 'email'],
            ['request_body', 'customer', 'phones'],
        ];

        foreach ($sensitivePaths as $path) {
            $this->maskPath($context, $path);
        }

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $context['exception'] = [
                'class' => get_class($context['exception']),
                'message' => $context['exception']->getMessage(),
            ];
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int|string> $path
     */
    private function maskPath(array &$payload, array $path): void
    {
        $cursor = &$payload;

        foreach ($path as $index => $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return;
            }

            if ($index === array_key_last($path)) {
                $cursor[$segment] = '***';
                return;
            }

            $cursor = &$cursor[$segment];
        }
    }
}
