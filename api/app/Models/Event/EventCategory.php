<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends BaseModel
{
    protected $table = 'event_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
