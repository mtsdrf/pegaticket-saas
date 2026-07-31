<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensagem da central de pendências tenant <-> contador (roadmap 2C). Sem
 * BaseModel — histórico imutável de conversa, escopado pelo vínculo aprovado.
 */
class AccountingRequestMessage extends Model
{
    use HasUuid;

    protected $table = 'accounting_request_messages';

    protected $fillable = [
        'uuid',
        'accounting_office_tenant_id',
        'tenant_id',
        'sender_type',
        'sender_user_id',
        'body',
        'due_date',
        'status',
        'attachment_path',
        'attachment_name',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    protected $hidden = [
        'id',
        'accounting_office_tenant_id',
        'sender_user_id',
        'attachment_path',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(AccountingOfficeTenant::class, 'accounting_office_tenant_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
