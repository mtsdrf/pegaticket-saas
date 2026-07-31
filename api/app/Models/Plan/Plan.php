<?php

namespace App\Models\Plan;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends BaseModel
{
    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function functionalities(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Functionality\Functionality::class,
            'plan_functionalities',
            'plan_id',
            'functionality_id'
        )->whereNull('plan_functionalities.deleted_at');
    }

    public function planFunctionalities(): HasMany
    {
        return $this->hasMany(PlanFunctionality::class, 'plan_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }
}
