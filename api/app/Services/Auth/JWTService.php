<?php

namespace App\Services\Auth;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JWTService
{
    public function issueAccessToken($user, ?int $tenantId = null, ?string $tenantUuid = null): string
    {
        $customClaims = [];

        if ($tenantId) {
            $customClaims['tenant_id'] = $tenantId;
            $customClaims['tenant_uuid'] = $tenantUuid;
        }

        return JWTAuth::claims($customClaims)->fromUser($user);
    }

    public function getPayload(string $token)
    {
        return JWTAuth::setToken($token)->getPayload();
    }
}