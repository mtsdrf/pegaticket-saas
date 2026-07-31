<?php

namespace App\Models\Legal;

use App\Models\BaseModel;

/**
 * Conteúdo global da plataforma (roadmap A1.6), sem tenant_id — mesmo
 * espírito de LegalDocument, sem exigir aceite (`legal_acceptances` não
 * tem equivalente aqui). Namespace App\Models\Legal por proximidade de
 * domínio (conteúdo institucional versionado), não por reuso de tabela.
 */
class ReleaseNote extends BaseModel
{
    protected $table = 'release_notes';

    protected $fillable = [
        'title',
        'body',
        'version',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}
