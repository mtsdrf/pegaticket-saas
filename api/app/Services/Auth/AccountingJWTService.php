<?php

namespace App\Services\Auth;

use App\Models\Accounting\AccountingOffice;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Emissão de JWT para a identidade AccountingOffice (contador), paralelo a
 * JWTService (User staff) e CustomerJWTService (cliente final). Mesmo
 * pacote/segredo/manager (config/jwt.php) — `lock_subject=true` embute a
 * claim `prv` (hash da classe do subject), o que impede um token de contador
 * de ser aceito como se fosse de User/FinalCustomer e vice-versa. Ver decisão
 * de arquitetura "JWT multi-identidade" em .claude/memory/api-patterns.md.
 */
class AccountingJWTService
{
    public function issueAccessToken(AccountingOffice $office): string
    {
        return JWTAuth::claims([])->fromUser($office);
    }
}
