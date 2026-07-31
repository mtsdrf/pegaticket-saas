<?php

namespace App\Listeners\Fiscal;

use App\Events\Fiscal\TaxRuleDeleted;
use App\Models\AuditLog;

class AuditTaxRuleDeleted
{
    public function handle(TaxRuleDeleted $event): void
    {
        AuditLog::record(
            event: 'tax_rule_deleted',
            model: null,
            meta: ['tax_rule_uuid' => $event->taxRuleUuid],
            actorId: $event->actorId
        );
    }
}
