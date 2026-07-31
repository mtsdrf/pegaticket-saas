<?php

namespace Tests\Unit\Fiscal;

use App\Models\Fiscal\TaxRule;
use App\Models\Tenant\Tenant;
use App\Services\Fiscal\TaxRuleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxRuleMatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefers_more_specific_rules(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Fiscal',
            'slug' => 'tenant-fiscal-' . Str::random(8),
            'is_active' => true,
        ]);

        $general = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'tax_type' => 'icms',
            'scope' => null,
            'rate_percent' => 18,
            'is_active' => true,
        ]);

        $specific = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'tax_type' => 'icms',
            'scope' => ['cfop' => ['5102'], 'uf_dest' => ['SP']],
            'rate_percent' => 12,
            'is_active' => true,
        ]);

        $matches = app(TaxRuleMatcher::class)->matchForTenant($tenant->id, [
            'tax_type' => 'icms',
            'cfop' => '5102',
            'uf_dest' => 'SP',
        ]);

        $this->assertCount(2, $matches);
        $this->assertSame($specific->uuid, $matches->first()->uuid);
        $this->assertSame($general->uuid, $matches->last()->uuid);
    }

    #[Test]
    public function it_matches_ncm_prefixes(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant NCM',
            'slug' => 'tenant-ncm-' . Str::random(8),
            'is_active' => true,
        ]);

        TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'tax_type' => 'ipi',
            'scope' => ['ncm' => ['2203']],
            'rate_percent' => 4,
            'is_active' => true,
        ]);

        $matches = app(TaxRuleMatcher::class)->matchForTenant($tenant->id, [
            'tax_type' => 'ipi',
            'ncm' => '22030000',
        ]);

        $this->assertCount(1, $matches);
    }
}
