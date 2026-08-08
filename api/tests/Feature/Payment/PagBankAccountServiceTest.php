<?php

namespace Tests\Feature\Payment;

use App\DTOs\Payment\CreatePagBankSellerAccountDTO;
use App\Exceptions\Payment\PagBankAccountException;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use App\Services\Payment\PagBankAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Caminho Account/Cadastro do PagBank Connect (roadmap fase R2.2).
 * Http::fake em todos os testes — nenhuma chamada de rede real, mesmo
 * padrão de PagBankConnectServiceTest.
 */
class PagBankAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private PagBankAccountService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-application-token');
        Config::set('services.pagbank.connect_client_id', 'fake-client-id');
        Config::set('services.pagbank.connect_client_secret', 'fake-client-secret');

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Account',
            'slug' => 'tenant-account-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->service = app(PagBankAccountService::class);
    }

    private function pfPayload(): array
    {
        return [
            'person_type' => 'pf',
            'email' => 'seller-pf@test.com',
            'terms_accepted_at' => now()->toIso8601String(),
            'person' => [
                'name' => 'Maria da Silva',
                'birth_date' => '1994-07-03',
                'mother_name' => 'Joana da Silva',
                'tax_id' => '111.444.777-35',
                'address' => [
                    'street' => 'Rua das Flores',
                    'number' => '100',
                    'complement' => 'Apto 1',
                    'locality' => 'Centro',
                    'city' => 'São Paulo',
                    'region_code' => 'SP',
                    'postal_code' => '01000-000',
                    'country' => 'BRA',
                ],
                'phone' => ['country' => '55', 'area' => '11', 'number' => '981111111'],
            ],
            'company' => null,
        ];
    }

    private function pjPayload(): array
    {
        $data = $this->pfPayload();
        $data['person_type'] = 'pj';
        $data['email'] = 'seller-pj@test.com';
        $data['company'] = [
            'name' => 'Eventos LTDA',
            'tax_id' => '11.222.333/0001-81',
            'address' => $data['person']['address'],
            'phone' => $data['person']['phone'],
        ];

        return $data;
    }

    #[Test]
    public function creating_a_pf_account_persists_the_connection_correctly(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_PF_1',
                'type' => 'SELLER',
                'email' => 'seller-pf@test.com',
                'token' => [
                    'access_token' => 'ACCESS-PF',
                    'refresh_token' => 'REFRESH-PF',
                    'expires_in' => 14400,
                    'scope' => 'payments.read',
                ],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());
        $connection = $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $this->assertSame(TenantPagBankConnection::STATUS_SUBMITTED, $connection->status);
        $this->assertSame(TenantPagBankConnection::CONNECTION_TYPE_CREATED_BY_PLATFORM, $connection->connection_type);
        $this->assertSame(TenantPagBankConnection::ACCOUNT_PERSON_TYPE_PF, $connection->account_person_type);
        $this->assertSame('ACCO_PF_1', $connection->account_id);
        $this->assertNotNull($connection->submitted_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/accounts'
                && $request->hasHeader('x-client-id', 'fake-client-id')
                && $request->hasHeader('x-client-secret', 'fake-client-secret')
                && $request['person']['tax_id'] === '11144477735'
                && ! isset($request['company']);
        });
    }

    #[Test]
    public function creating_a_pj_account_persists_the_connection_correctly(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_PJ_1',
                'type' => 'SELLER',
                'token' => [
                    'access_token' => 'ACCESS-PJ',
                    'refresh_token' => 'REFRESH-PJ',
                    'expires_in' => 14400,
                ],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pjPayload());
        $connection = $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $this->assertSame(TenantPagBankConnection::ACCOUNT_PERSON_TYPE_PJ, $connection->account_person_type);
        $this->assertSame('ACCO_PJ_1', $connection->account_id);

        Http::assertSent(function ($request) {
            return $request['company']['tax_id'] === '11222333000181'
                && $request['person']['tax_id'] === '11144477735';
        });
    }

    #[Test]
    public function creating_a_duplicate_account_is_rejected(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_1',
                'token' => ['access_token' => 'A', 'refresh_token' => 'R', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());
        $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $this->expectException(PagBankAccountException::class);
        $this->expectExceptionMessage('pagbank_account.already_submitted');

        $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');
    }

    #[Test]
    public function document_is_never_stored_in_plain_text(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_1',
                'token' => ['access_token' => 'ACCESS-X', 'refresh_token' => 'REFRESH-X', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());
        $connection = $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $rawDocument = DB::table('tenant_pagbank_connections')->where('id', $connection->id)->value('document_encrypted');
        $rawAccessToken = DB::table('tenant_pagbank_connections')->where('id', $connection->id)->value('access_token_encrypted');

        $this->assertNotSame('11144477735', $rawDocument);
        $this->assertNotSame('ACCESS-X', $rawAccessToken);
        $this->assertSame('11144477735', $connection->document_encrypted);
    }

    #[Test]
    public function document_and_tokens_never_appear_in_the_json_representation(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_1',
                'token' => ['access_token' => 'ACCESS-X', 'refresh_token' => 'REFRESH-X', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());
        $connection = $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        $json = $connection->toArray();

        $this->assertArrayNotHasKey('document_encrypted', $json);
        $this->assertArrayNotHasKey('access_token_encrypted', $json);
        $this->assertArrayNotHasKey('refresh_token_encrypted', $json);
    }

    #[Test]
    public function creation_failure_marks_the_connection_as_error(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response(['error_messages' => []], 400),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());

        try {
            $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');
            $this->fail('Expected PagBankAccountException');
        } catch (PagBankAccountException $e) {
            $this->assertSame('pagbank_account.creation_failed', $e->getMessage());
        }

        $connection = TenantPagBankConnection::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(TenantPagBankConnection::STATUS_ERROR, $connection->status);
    }

    #[Test]
    public function sync_status_maps_active_provider_status_to_verified(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/accounts' => Http::response([
                'id' => 'ACCO_1',
                'token' => ['access_token' => 'ACCESS-X', 'refresh_token' => 'REFRESH-X', 'expires_in' => 100],
            ], 201),
        ]);

        $dto = CreatePagBankSellerAccountDTO::fromArray($this->pfPayload());
        $connection = $this->service->createSellerAccount($this->tenant->id, $dto, '127.0.0.1');

        Http::fake([
            'sandbox.api.pagseguro.com/accounts/ACCO_1' => Http::response([
                'id' => 'ACCO_1',
                'status' => 'ACTIVE',
            ], 200),
        ]);

        $result = $this->service->syncStatus($connection);

        $this->assertSame(TenantPagBankConnection::STATUS_VERIFIED, $result->status);
        $this->assertSame('ACTIVE', $result->provider_status);
        $this->assertNotNull($result->verified_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/accounts/ACCO_1'
                && $request->hasHeader('x-client-token', 'ACCESS-X');
        });
    }
}
