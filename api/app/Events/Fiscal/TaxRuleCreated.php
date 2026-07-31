<?php

namespace App\Events\Fiscal;

class TaxRuleCreated
{
    public function __construct(
        public string $taxRuleUuid,
        public int $actorId
    ) {
    }
}
