<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\TaxRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TaxRuleMatcher
{
    public function matchForTenant(int $tenantId, array $context, ?CarbonInterface $at = null): Collection
    {
        $at = $at ?? now();

        $query = TaxRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($at) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($query) use ($at) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $at);
            });

        if (!empty($context['tax_type'])) {
            $query->where('tax_type', $context['tax_type']);
        }

        return $query
            ->get()
            ->filter(fn (TaxRule $rule) => $this->scopeMatches($rule, $context))
            ->sortByDesc(fn (TaxRule $rule) => $this->specificityScore((array) ($rule->scope ?? [])))
            ->values();
    }

    private function scopeMatches(TaxRule $rule, array $context): bool
    {
        $scope = (array) ($rule->scope ?? []);

        return $this->matchesList($scope['cfop'] ?? null, $context['cfop'] ?? null, true)
            && $this->matchesList($scope['ncm'] ?? null, $context['ncm'] ?? null, false)
            && $this->matchesList($scope['uf_origin'] ?? null, $context['uf_origin'] ?? null, true)
            && $this->matchesList($scope['uf_dest'] ?? null, $context['uf_dest'] ?? null, true);
    }

    private function matchesList(?array $list, ?string $value, bool $exact): bool
    {
        if (empty($list)) {
            return true;
        }

        if ($value === null || $value === '') {
            return false;
        }

        foreach ($list as $candidate) {
            $candidate = (string) $candidate;
            if ($exact && $candidate === $value) {
                return true;
            }

            if (!$exact && str_starts_with($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function specificityScore(array $scope): int
    {
        $score = 0;
        foreach (['cfop', 'ncm', 'uf_origin', 'uf_dest'] as $key) {
            if (!empty($scope[$key])) {
                $score += 10;
            }
        }

        return $score;
    }
}
