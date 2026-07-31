<?php

namespace Tests\Feature\Permissions;

use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected ProductCategory $category;
    protected ProductType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('media.public_disks.product'));

        $this->setUpTenantScopedUser('product-user@test.com');

        $this->category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Drinks',
            'is_active' => true,
        ]);

        $this->type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Soda',
            'is_active' => true,
        ]);
    }

    protected function basePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Coca-Cola 2L',
            'price' => 12.90,
            'product_type_uuid' => $this->type->uuid,
        ], $overrides);
    }

    #[Test]
    public function user_without_permission_cannot_list_products(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products')
            ->assertStatus(403);
    }

    #[Test]
    public function user_with_read_permission_can_list_products(): void
    {
        $this->grantPermission('products', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products')
            ->assertStatus(200);
    }

    #[Test]
    public function user_with_create_permission_can_create_product_with_valid_type(): void
    {
        $this->grantPermission('products', 'create');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload())
            ->assertStatus(201);

        $response->assertJsonPath('data.name', 'Coca-Cola 2L');
        $response->assertJsonPath('data.price', 12.9);
        $response->assertJsonPath('data.product_type.uuid', $this->type->uuid);
        $response->assertJsonPath('data.product_type.product_category.uuid', $this->category->uuid);

        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function create_product_can_persist_option_groups_and_options(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload([
                'option_groups' => [
                    [
                        'name' => 'Bebidas',
                        'description' => 'Escolha uma bebida',
                        'min_select' => 0,
                        'max_select' => 1,
                        'sort_order' => 1,
                        'is_active' => true,
                        'options' => [
                            [
                                'name' => 'Coca-Cola lata',
                                'price' => 6.5,
                                'sort_order' => 1,
                                'is_available' => true,
                            ],
                            [
                                'name' => 'Guaraná lata',
                                'price' => 5.5,
                                'sort_order' => 2,
                                'is_available' => true,
                            ],
                        ],
                    ],
                ],
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.option_groups.0.name', 'Bebidas');
        $response->assertJsonPath('data.option_groups.0.options.0.name', 'Coca-Cola lata');
        $response->assertJsonPath('data.option_groups.0.options.1.price', 5.5);

        $productUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('product_option_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
        ]);

        $this->assertDatabaseHas('product_options', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Guaraná lata',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products/' . $productUuid)
            ->assertStatus(200)
            ->assertJsonPath('data.option_groups.0.options.0.name', 'Coca-Cola lata');
    }

    #[Test]
    public function option_group_kind_defaults_to_addon_and_can_be_ingredient_removal(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload([
                'option_groups' => [
                    [
                        'name' => 'Bebidas',
                        'min_select' => 0,
                        'max_select' => 1,
                        'options' => [
                            ['name' => 'Coca-Cola lata', 'price' => 6.5, 'is_available' => true],
                        ],
                    ],
                    [
                        'name' => 'Remover ingredientes',
                        'kind' => 'ingredient_removal',
                        'min_select' => 0,
                        'max_select' => 5,
                        'options' => [
                            ['name' => 'Sem cebola', 'price' => 0, 'is_available' => true],
                            ['name' => 'Sem tomate', 'price' => 0, 'is_available' => true],
                        ],
                    ],
                ],
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('data.option_groups.0.kind', 'addon');
        $response->assertJsonPath('data.option_groups.1.kind', 'ingredient_removal');
        $response->assertJsonPath('data.option_groups.1.options.0.price', 0);

        $this->assertDatabaseHas('product_option_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Remover ingredientes',
            'kind' => 'ingredient_removal',
        ]);
    }

    #[Test]
    public function creating_product_without_name_or_type_fails_validation(): void
    {
        $this->grantPermission('products', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'price', 'product_type_uuid']]);
    }

    #[Test]
    public function creating_product_with_duplicate_sku_in_same_tenant_fails_validation(): void
    {
        $this->grantPermission('products', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['sku' => 'SKU-001']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Another Product', 'sku' => 'SKU-001']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['sku']]);
    }

    #[Test]
    public function updating_product_without_changing_own_sku_does_not_fail_validation(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['sku' => 'SKU-002']))
            ->assertStatus(201);

        $uuid = $response->json('data.uuid');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/products/' . $uuid, ['sku' => 'SKU-002', 'price' => 15.00])
            ->assertStatus(200)
            ->assertJsonPath('data.sku', 'SKU-002');
    }

    #[Test]
    public function update_product_replaces_option_group_structure(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'update');
        $this->grantPermission('products', 'read');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload([
                'option_groups' => [
                    [
                        'name' => 'Molhos',
                        'min_select' => 0,
                        'max_select' => 2,
                        'options' => [
                            ['name' => 'Barbecue', 'price' => 2.5],
                            ['name' => 'Mostarda e mel', 'price' => 2.0],
                        ],
                    ],
                ],
            ]))
            ->assertStatus(201)
            ->json('data');

        $groupUuid = $created['option_groups'][0]['uuid'];
        $optionUuid = $created['option_groups'][0]['options'][0]['uuid'];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/products/' . $created['uuid'], [
                'option_groups' => [
                    [
                        'uuid' => $groupUuid,
                        'name' => 'Molhos e extras',
                        'min_select' => 1,
                        'max_select' => 2,
                        'options' => [
                            [
                                'uuid' => $optionUuid,
                                'name' => 'Barbecue especial',
                                'price' => 3.5,
                                'sort_order' => 1,
                                'is_available' => true,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.option_groups.0.name', 'Molhos e extras')
            ->assertJsonPath('data.option_groups.0.min_select', 1)
            ->assertJsonPath('data.option_groups.0.options.0.name', 'Barbecue especial')
            ->assertJsonCount(1, 'data.option_groups.0.options');

        $this->assertDatabaseHas('product_option_groups', [
            'uuid' => $groupUuid,
            'name' => 'Molhos e extras',
            'min_select' => 1,
        ]);

        $this->assertDatabaseHas('product_options', [
            'uuid' => $optionUuid,
            'name' => 'Barbecue especial',
            'price' => 3.50,
        ]);

        $this->assertSoftDeleted('product_options', [
            'name' => 'Mostarda e mel',
        ]);
    }

    #[Test]
    public function same_sku_is_allowed_across_different_tenants(): void
    {
        $this->grantPermission('products', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['sku' => 'SKU-SHARED']))
            ->assertStatus(201);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Drinks',
            'is_active' => true,
        ]);

        $foreignType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $foreignCategory->id,
            'name' => 'Foreign Soda',
            'is_active' => true,
        ]);

        Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_type_id' => $foreignType->id,
            'name' => 'Foreign Product',
            'price' => 9.90,
            'sku' => 'SKU-SHARED',
            'is_available' => true,
        ]);

        $this->assertDatabaseCount('products', 2);
    }

    #[Test]
    public function multiple_products_without_sku_are_allowed_in_same_tenant(): void
    {
        $this->grantPermission('products', 'create');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Product A']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Product B']))
            ->assertStatus(201);

        $this->assertDatabaseCount('products', 2);
    }

    #[Test]
    public function user_cannot_create_product_with_type_from_another_tenant(): void
    {
        $this->grantPermission('products', 'create');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $foreignType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['product_type_uuid' => $foreignType->uuid]))
            ->assertStatus(422);
    }

    #[Test]
    public function product_image_upload_is_stored_and_url_exposed(): void
    {
        $this->grantPermission('products', 'create');

        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/products', $this->basePayload(['image' => $image]))
            ->assertStatus(201);

        $imageUrl = $response->json('data.image_url');
        $this->assertNotNull($imageUrl);

        $product = Product::where('uuid', $response->json('data.uuid'))->first();
        $this->assertNotNull($product->image_path);
        $this->assertNotNull($product->image_mime);
        $this->assertNotNull($product->image_updated_at);
        $this->assertStringStartsWith($this->tenant->uuid . '/', $product->image_path);
        Storage::disk((string) config('media.public_disks.product'))->assertExists($product->image_path);
    }

    #[Test]
    public function updating_product_image_replaces_old_data(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'update');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/products', $this->basePayload(['image' => UploadedFile::fake()->image('old.jpg', 100, 100)]))
            ->assertStatus(201)
            ->json('data');

        $originalProduct = Product::where('uuid', $created['uuid'])->first();
        $oldPath = $originalProduct->image_path;
        $this->assertNotNull($oldPath);
        Storage::disk((string) config('media.public_disks.product'))->assertExists($oldPath);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put('/api/v1/products/' . $created['uuid'], [
                'image' => UploadedFile::fake()->image('new.jpg', 50, 50),
            ])
            ->assertStatus(200);

        $updatedProduct = Product::where('uuid', $created['uuid'])->first();
        $newPath = $updatedProduct->image_path;

        $this->assertNotEquals($oldPath, $newPath);
        $this->assertStringStartsWith($this->tenant->uuid . '/', $newPath);
        Storage::disk((string) config('media.public_disks.product'))->assertMissing($oldPath);
        Storage::disk((string) config('media.public_disks.product'))->assertExists($newPath);
        $this->assertNotNull($response->json('data.image_url'));
    }

    #[Test]
    public function get_product_image_returns_bytes_with_correct_content_type(): void
    {
        $this->grantPermission('products', 'create');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/products', $this->basePayload(['image' => UploadedFile::fake()->image('product.jpg')]))
            ->assertStatus(201)
            ->json('data');

        $product = Product::where('uuid', $created['uuid'])->first();

        $response = $this->get('/api/v1/products/' . $product->uuid . '/image');

        $response->assertStatus(200);
        $this->assertEquals($product->image_mime, $response->headers->get('Content-Type'));
        $this->assertEquals(Storage::disk((string) config('media.public_disks.product'))->get($product->image_path), $response->getContent());
    }

    #[Test]
    public function get_product_image_returns_404_when_product_has_no_image(): void
    {
        $this->grantPermission('products', 'create');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/products', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $this->get('/api/v1/products/' . $created['uuid'] . '/image')
            ->assertStatus(404);
    }

    #[Test]
    public function user_with_read_permission_can_show_own_product(): void
    {
        $this->grantPermission('products', 'read');

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $this->type->id,
            'name' => 'Guarana 2L',
            'price' => 9.90,
            'is_available' => true,
            'stock_quantity' => 10,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products/' . $product->uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $product->uuid)
            ->assertJsonPath('data.product_type.uuid', $this->type->uuid);
    }

    #[Test]
    public function user_cannot_show_product_from_another_tenant(): void
    {
        $this->grantPermission('products', 'read');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $foreignProduct = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_type_id' => $otherType->id,
            'name' => 'Foreign Product',
            'price' => 5.00,
            'is_available' => true,
            'stock_quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products/' . $foreignProduct->uuid)
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_update_product_from_another_tenant(): void
    {
        $this->grantPermission('products', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $foreignProduct = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_type_id' => $otherType->id,
            'name' => 'Foreign Product',
            'price' => 5.00,
            'is_available' => true,
            'stock_quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/products/' . $foreignProduct->uuid, ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    #[Test]
    public function user_cannot_delete_product_from_another_tenant(): void
    {
        $this->grantPermission('products', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Foreign Type',
            'is_active' => true,
        ]);

        $foreignProduct = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_type_id' => $otherType->id,
            'name' => 'Foreign Product',
            'price' => 5.00,
            'is_available' => true,
            'stock_quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/products/' . $foreignProduct->uuid)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreignProduct);
    }

    #[Test]
    public function user_can_delete_own_product(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'delete');

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload())
            ->assertStatus(201)
            ->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/products/' . $created['uuid'])
            ->assertStatus(204);

        $this->assertSoftDeleted('products', ['uuid' => $created['uuid']]);
    }

    #[Test]
    public function listing_can_be_filtered_by_is_available_false(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Available Product', 'is_available' => true]))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Unavailable Product', 'is_available' => false]))
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?is_available=0')
            ->assertStatus(200);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Unavailable Product');
    }

    #[Test]
    public function listing_can_be_filtered_by_product_type_and_category_uuid(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $this->category->id,
            'name' => 'Water',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Soda Product']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Water Product', 'product_type_uuid' => $otherType->uuid]))
            ->assertStatus(201);

        $byType = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?product_type_uuid=' . $otherType->uuid)
            ->assertStatus(200);

        $byType->assertJsonCount(1, 'data');
        $byType->assertJsonPath('data.0.name', 'Water Product');

        $byCategory = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?product_category_uuid=' . $this->category->uuid)
            ->assertStatus(200);

        $byCategory->assertJsonCount(2, 'data');
    }

    #[Test]
    public function listing_can_be_sorted_by_product_category_name_and_filtered_by_price_range(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Chips',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Coca-Cola 2L', 'price' => 12.90]))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload([
                'name' => 'Batata Chips',
                'price' => 7.50,
                'product_type_uuid' => $otherType->uuid,
            ]))
            ->assertStatus(201);

        $sorted = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?sort_by=product_category_name&sort_dir=asc')
            ->assertStatus(200);

        $sorted->assertJsonPath('data.0.name', 'Coca-Cola 2L');
        $sorted->assertJsonPath('data.1.name', 'Batata Chips');

        $byTypeName = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?product_type_name=Chips')
            ->assertStatus(200);

        $byTypeName->assertJsonCount(1, 'data');
        $byTypeName->assertJsonPath('data.0.name', 'Batata Chips');

        $byPriceRange = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?price_min=10&price_max=20')
            ->assertStatus(200);

        $byPriceRange->assertJsonCount(1, 'data');
        $byPriceRange->assertJsonPath('data.0.name', 'Coca-Cola 2L');
    }

    #[Test]
    public function listing_can_be_searched_globally_with_q_across_name_type_and_category(): void
    {
        $this->grantPermission('products', 'create');
        $this->grantPermission('products', 'read');

        $otherCategory = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $otherType = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $otherCategory->id,
            'name' => 'Chips',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload(['name' => 'Coca-Cola 2L']))
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/products', $this->basePayload([
                'name' => 'Batata Chips',
                'product_type_uuid' => $otherType->uuid,
            ]))
            ->assertStatus(201);

        // match por name do produto
        $byName = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?q=Coca')
            ->assertStatus(200);
        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.name', 'Coca-Cola 2L');

        // match por product_type_name (relação productType)
        $byType = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?q=Chips')
            ->assertStatus(200);
        $byType->assertJsonCount(1, 'data');
        $byType->assertJsonPath('data.0.name', 'Batata Chips');

        // match por product_category_name (relação productType.productCategory)
        $byCategory = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?q=Snacks')
            ->assertStatus(200);
        $byCategory->assertJsonCount(1, 'data');
        $byCategory->assertJsonPath('data.0.name', 'Batata Chips');

        $noMatch = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/products?q=Nonexistent')
            ->assertStatus(200);
        $noMatch->assertJsonCount(0, 'data');
    }
}
