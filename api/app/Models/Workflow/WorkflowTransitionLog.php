<?php

namespace App\Models\Workflow;

use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'workflow_type',
        'entity_id',
        'entity_uuid',
        'from_stage',
        'to_stage',
        'transition_type',
        'reason',
        'meta',
        'moved_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'moved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
