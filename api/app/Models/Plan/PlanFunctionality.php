<?php

namespace App\Models\Plan;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFunctionality extends BaseModel
{
    protected $table = 'plan_functionalities';

    protected $fillable = [
        'plan_id',
        'functionality_id',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function functionality(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Functionality\Functionality::class, 'functionality_id');
    }
}
