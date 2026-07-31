<?php

namespace App\Listeners\Fiscal;

use App\Events\Fiscal\TaxRuleCreated;
use App\Models\AuditLog;

class AuditTaxRuleCreated
{
    public function handle(TaxRuleCreated $event): void
    {
        AuditLog::record(
            event: 'tax_rule_created',
            model: null,
            meta: ['tax_rule_uuid' => $event->taxRuleUuid],
            actorId: $event->actorId
        );
    }
}
