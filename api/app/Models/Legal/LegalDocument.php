<?php

namespace App\Models\Legal;

use App\Models\BaseModel;

class LegalDocument extends BaseModel
{
    protected $table = 'legal_documents';

    protected $fillable = [
        'type',
        'version',
        'content',
        'published_at',
        'is_active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}
