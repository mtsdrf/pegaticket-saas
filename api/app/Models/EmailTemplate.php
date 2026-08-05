<?php

namespace App\Models;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override configurável de assunto/corpo de um e-mail transacional por
 * `type` (mesma chave de CommunicationDispatcherService/CommunicationLog),
 * opcionalmente por tenant. Ver App\Services\Communication\EmailTemplateResolverService
 * para a lógica de fallback (sem override -> Mailable usa texto/view padrão)
 * e App\Services\EmailTemplate\EmailTemplateService para as regras de CRUD
 * (quais types podem ser customizados por tenant).
 */
class EmailTemplate extends BaseModel
{
    protected $table = 'email_templates';

    protected $fillable = [
        'tenant_id',
        'type',
        'subject',
        'body_html',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
