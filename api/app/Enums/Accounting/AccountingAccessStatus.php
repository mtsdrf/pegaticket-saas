<?php

namespace App\Enums\Accounting;

/**
 * Estados do vínculo contador <-> tenant (roadmap 2C). A coluna
 * accounting_office_tenant.status é string no banco — este enum é a fonte de
 * verdade dos valores válidos.
 */
enum AccountingAccessStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revoked = 'revoked';
}
