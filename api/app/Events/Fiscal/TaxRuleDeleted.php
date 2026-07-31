<?php

namespace App\Events\Fiscal;

class TaxRuleDeleted
{
    public function __construct(
        public string $taxRuleUuid,
        public int $actorId
    ) {
    }
}
