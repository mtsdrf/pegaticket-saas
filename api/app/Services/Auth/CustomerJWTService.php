<?php

namespace App\Services\Auth;

use App\Models\FinalCustomer\FinalCustomer;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Emissão de JWT para a identidade FinalCustomer (cliente final, portal
 * cross-tenant), paralelo a JWTService (que emite para App\Models\User\User,
 * o usuário STAFF). Ver decisão de arquitetura completa em
 * .claude/memory/api-patterns.md ("JWT multi-identidade").
 *
 * Reaproveita o MESMO pacote/manager/segredo já configurado
 * (config/jwt.php) — não é um sistema de token paralelo, é o mesmo
 * `JWTAuth::fromUser()` já usado por JWTService, só que com um
 * JWTSubject diferente. `lock_subject=true` (default do projeto) já
 * embute automaticamente a claim `prv` = hash da classe do subject
 * (App\Models\FinalCustomer\FinalCustomer::class) — é isso que impede um
 * token de FinalCustomer de ser aceito como se fosse de User e vice-versa
 * (ver CustomerJwtAccessMiddleware e o guard equivalente adicionado a
 * JwtAccessMiddleware).
 */
class CustomerJWTService
{
    public function issueAccessToken(FinalCustomer $customer): string
    {
        return JWTAuth::claims([])->fromUser($customer);
    }
}
