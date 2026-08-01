<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * FinalCustomer (portal do cliente final) e User (staff) são identidades
 * JWT DISTINTAS emitidas com o mesmo segredo/manager — um token de uma
 * nunca pode autenticar como a outra, mesmo que os ids numéricos (`sub`)
 * colidam entre as tabelas. Ver a decisão de arquitetura completa em
 * .claude/memory/api-patterns.md ("JWT multi-identidade"): a proteção vem
 * da claim `prv` (lock_subject=true), checada em
 * App\Http\Middleware\CustomerJwtAccessMiddleware (token de FinalCustomer)
 * e, desde esta feature, também em App\Http\Middleware\JwtAccessMiddleware
 * (token de User).
 */
class PortalIdentityBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    #[Test]
    public function a_staff_user_token_is_rejected_on_portal_routes(): void
    {
        $this->setUpTenantScopedUser('staff@test.com');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/portal/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/portal/sales')
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => (string) Str::uuid()])
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');
    }

    #[Test]
    public function a_final_customer_token_is_rejected_on_staff_routes(): void
    {
        $customer = FinalCustomer::create(['email' => 'cliente@test.com']);
        $customerToken = app(CustomerJWTService::class)->issueAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/auth/my-tenants')
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');
    }
}
