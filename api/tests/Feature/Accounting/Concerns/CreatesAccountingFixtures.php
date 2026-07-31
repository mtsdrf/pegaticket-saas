<?php

namespace Tests\Feature\Accounting\Concerns;

use App\Enums\Accounting\AccountingAccessStatus;
use App\Models\Accounting\AccountingOffice;
use App\Models\Accounting\AccountingOfficeTenant;
use App\Models\Tenant\Tenant;
use App\Services\Auth\AccountingJWTService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

trait CreatesAccountingFixtures
{
    protected function google2fa(): Google2FA
    {
        return new Google2FA();
    }

    protected function makeOffice(array $overrides = []): AccountingOffice
    {
        $secret = $this->google2fa()->generateSecretKey();

        return AccountingOffice::create(array_merge([
            'cnpj' => '11222333000181',
            'company_name' => 'Escritório ' . Str::random(5),
            'responsible_name' => 'Contador ' . Str::random(5),
            'email' => 'office-' . Str::random(8) . '@example.com',
            'password_hash' => Hash::make('password123'),
            'totp_secret' => $secret,
            'totp_enabled_at' => now(),
        ], $overrides));
    }

    protected function officeToken(AccountingOffice $office): string
    {
        return app(AccountingJWTService::class)->issueAccessToken($office);
    }

    protected function currentTotp(AccountingOffice $office): string
    {
        return $this->google2fa()->getCurrentOtp($office->totp_secret);
    }

    protected function makeTenantWithCnpj(string $cnpj, array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa ' . Str::random(5),
            'slug' => 'empresa-' . Str::random(8),
            'cnpj' => $cnpj,
            'is_active' => true,
        ], $overrides));
    }

    protected function approveLink(AccountingOffice $office, Tenant $tenant, array $scopes = ['financial.read'], ?int $approvedBy = null): AccountingOfficeTenant
    {
        return AccountingOfficeTenant::create([
            'accounting_office_id' => $office->id,
            'tenant_id' => $tenant->id,
            'status' => AccountingAccessStatus::Approved->value,
            'scopes' => $scopes,
            'requested_at' => now(),
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }
}
