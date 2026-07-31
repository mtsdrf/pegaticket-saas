<?php

namespace App\Listeners\Fiscal;

use App\Events\Fiscal\TaxRuleUpdated;
use App\Models\AuditLog;

class AuditTaxRuleUpdated
{
    public function handle(TaxRuleUpdated $event): void
    {
        AuditLog::record(
            event: 'tax_rule_updated',
            model: null,
            meta: ['tax_rule_uuid' => $event->taxRuleUuid],
            actorId: $event->actorId
        );
    }
}
