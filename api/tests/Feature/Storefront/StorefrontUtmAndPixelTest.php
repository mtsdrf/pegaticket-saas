<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Fase 6 — captura/propagação de UTM no checkout público (fatia 2) e
 * exposição de pixels de marketing por tenant (fatia 3), com isolamento
 * multi-tenant garantido (pixel de um tenant nunca aparece pra outro).
 */
class StorefrontUtmAndPixelTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    private function authenticatedCustomer(string $email = 'utm-comprador@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    #[Test]
    public function checkout_persists_utm_fields_onto_the_sale(): void
    {
        [, $token] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 40]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'utm_campaign' => 'lancamento-evento',
            ]);

        $response->assertStatus(201);
        $saleUuid = $response->json('data.sale.uuid');

        $sale = Sale::where('uuid', $saleUuid)->firstOrFail();
        $this->assertSame('instagram', $sale->utm_source);
        $this->assertSame('social', $sale->utm_medium);
        $this->assertSame('lancamento-evento', $sale->utm_campaign);
    }

    #[Test]
    public function checkout_without_utm_leaves_the_fields_null(): void
    {
        [, $token] = $this->authenticatedCustomer('sem-utm@test.com');
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 40]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ])->assertStatus(201);

        $sale = Sale::where('uuid', $response->json('data.sale.uuid'))->firstOrFail();
        $this->assertNull($sale->utm_source);
        $this->assertNull($sale->utm_medium);
        $this->assertNull($sale->utm_campaign);
    }

    #[Test]
    public function public_storefront_show_exposes_configured_pixel_ids(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'meta_pixel_id' => '1234567890',
            'google_analytics_id' => 'G-ABCDEF1234',
        ]);

        $response = $this->getJson("/api/v1/loja/{$tenant->slug}")->assertStatus(200);

        $this->assertSame('1234567890', $response->json('data.meta_pixel_id'));
        $this->assertSame('G-ABCDEF1234', $response->json('data.google_analytics_id'));
    }

    #[Test]
    public function public_storefront_show_returns_null_pixel_ids_when_not_configured(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);

        $response = $this->getJson("/api/v1/loja/{$tenant->slug}")->assertStatus(200);

        $this->assertNull($response->json('data.meta_pixel_id'));
        $this->assertNull($response->json('data.google_analytics_id'));
    }

    #[Test]
    public function a_tenants_pixel_ids_never_leak_into_another_tenants_storefront(): void
    {
        $tenantA = $this->createTenantWithStorefrontPlan(true);
        $tenantB = $this->createTenantWithStorefrontPlan(true);

        TenantSettings::create([
            'tenant_id' => $tenantA->id,
            'meta_pixel_id' => 'pixel-only-tenant-a',
            'google_analytics_id' => 'ga-only-tenant-a',
        ]);

        $responseB = $this->getJson("/api/v1/loja/{$tenantB->slug}")->assertStatus(200);

        $this->assertNull($responseB->json('data.meta_pixel_id'));
        $this->assertNull($responseB->json('data.google_analytics_id'));
    }

    #[Test]
    public function tenant_settings_endpoint_persists_pixel_ids_for_staff(): void
    {
        $this->setUpTenantScopedUser('pixel-staff@test.com');
        $this->grantPermission('tenant_settings', 'update');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/tenant-settings', [
                'meta_pixel_id' => '999888777',
                'google_analytics_id' => 'G-STAFFSET1',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.meta_pixel_id', '999888777')
            ->assertJsonPath('data.google_analytics_id', 'G-STAFFSET1');

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'meta_pixel_id' => '999888777',
            'google_analytics_id' => 'G-STAFFSET1',
        ]);
    }
}
