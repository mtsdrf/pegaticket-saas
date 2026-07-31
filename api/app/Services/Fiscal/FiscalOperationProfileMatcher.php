<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalOperationProfile;
use Illuminate\Support\Collection;

class FiscalOperationProfileMatcher
{
    public function matchForTenant(int $tenantId, array $context): ?FiscalOperationProfile
    {
        return $this->candidatesForTenant($tenantId)
            ->filter(fn (FiscalOperationProfile $profile) => $this->matchesScope($profile->scope, $context))
            ->sortByDesc(fn (FiscalOperationProfile $profile) => $this->specificityScore($profile->scope, $context))
            ->first();
    }

    /**
     * @return Collection<int, FiscalOperationProfile>
     */
    public function candidatesForTenant(int $tenantId): Collection
    {
        return FiscalOperationProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function matchesScope(?array $scope, array $context): bool
    {
        if (!$scope) {
            return true;
        }

        foreach (['order_origin', 'fulfillment_type', 'destination_type'] as $key) {
            $values = $scope[$key] ?? null;
            if (!is_array($values) || $values === []) {
                continue;
            }

            $current = $context[$key] ?? null;
            if (!is_string($current) || !in_array($current, $values, true)) {
                return false;
            }
        }

        return true;
    }

    private function specificityScore(?array $scope, array $context): int
    {
        if (!$scope) {
            return 0;
        }

        $score = 0;

        foreach (['order_origin', 'fulfillment_type', 'destination_type'] as $key) {
            $values = $scope[$key] ?? null;
            if (!is_array($values) || $values === []) {
                continue;
            }

            $current = $context[$key] ?? null;
            if (is_string($current) && in_array($current, $values, true)) {
                $score += 10;
                $score += max(0, 5 - count($values));
            }
        }

        return $score;
    }
}
