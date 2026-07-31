<?php

namespace Tests\Feature\Product;

use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ToggleAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('toggle-availability@test.com');
        $this->grantPermission('products', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function toggles_availability_without_a_body(): void
    {
        $product = $this->createProduct($this->tenant->id, ['is_available' => true]);

        $response = $this->auth()->patchJson("/api/v1/products/{$product->uuid}/toggle-availability");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('message', __('messages.product.availability_disabled'));

        $this->auth()->patchJson("/api/v1/products/{$product->uuid}/toggle-availability")
            ->assertStatus(200)
            ->assertJsonPath('data.is_available', true);
    }

    #[Test]
    public function sets_availability_explicitly_when_informed(): void
    {
        $product = $this->createProduct($this->tenant->id, ['is_available' => true]);

        $this->auth()->patchJson("/api/v1/products/{$product->uuid}/toggle-availability", [
            'is_available' => false,
        ])->assertStatus(200)->assertJsonPath('data.is_available', false);

        $this->auth()->patchJson("/api/v1/products/{$product->uuid}/toggle-availability", [
            'is_available' => false,
        ])->assertStatus(200)->assertJsonPath('data.is_available', false);
    }

    #[Test]
    public function cannot_toggle_a_product_from_another_tenant(): void
    {
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherProduct = $this->createProduct($otherTenant->id, ['is_available' => true]);

        $this->auth()->patchJson("/api/v1/products/{$otherProduct->uuid}/toggle-availability")
            ->assertStatus(404);
    }
}
