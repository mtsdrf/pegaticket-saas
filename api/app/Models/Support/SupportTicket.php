<?php

namespace App\Models\Support;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central de chamados nativa (roadmap A4, item 17). Reaproveita o padrão de
 * dado de `accounting_request_messages` (anexo opcional, status simples).
 */
class SupportTicket extends BaseModel
{
    public const STATUS_OPEN = 'open';

    protected $table = 'support_tickets';

    protected $fillable = [
        'tenant_id',
        'subject',
        'description',
        'status',
        'attachment_path',
        'attachment_name',
        'diagnostics',
    ];

    protected $casts = [
        'diagnostics' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'attachment_path',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
