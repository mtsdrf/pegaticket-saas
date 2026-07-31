<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan\PlanFunctionality;
use App\Repositories\Contracts\PlanFunctionalityRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanFunctionalityRepository extends BaseRepository implements PlanFunctionalityRepositoryInterface
{
    public function __construct(PlanFunctionality $model)
    {
        parent::__construct($model);
    }

    public function getPlanFunctionalities(int $planId): Collection
    {
        return DB::table('plan_functionalities as pf')
            ->join('functionalities as f', 'f.id', '=', 'pf.functionality_id')
            ->where('pf.plan_id', $planId)
            ->whereNull('pf.deleted_at')
            ->whereNull('f.deleted_at')
            ->orderBy('f.name')
            ->select('f.slug as functionality')
            ->get();
    }

    public function syncFunctionalities(int $planId, array $functionalities): void
    {
        DB::table('plan_functionalities')
            ->where('plan_id', $planId)
            ->delete();

        $functionalityIds = DB::table('functionalities')
            ->whereIn('slug', $functionalities)
            ->whereNull('deleted_at')
            ->pluck('id', 'slug');

        foreach ($functionalities as $slug) {
            $functionalityId = $functionalityIds[$slug] ?? null;

            if (!$functionalityId) {
                continue;
            }

            PlanFunctionality::create([
                'plan_id' => $planId,
                'functionality_id' => $functionalityId,
            ]);
        }
    }
}
