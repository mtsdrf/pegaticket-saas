<?php

namespace App\Exceptions\Fiscal;

use RuntimeException;

class FiscalPreparationBlockedException extends RuntimeException
{
    /**
     * @param array<int, array{key:string,label:string,severity:string,details:string}> $issues
     */
    public function __construct(
        string $message,
        private readonly array $issues = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{key:string,label:string,severity:string,details:string}>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}
