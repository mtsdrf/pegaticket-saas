<?php

namespace App\Events\Fiscal;

class TaxRuleUpdated
{
    public function __construct(
        public string $taxRuleUuid,
        public int $actorId
    ) {
    }
}
