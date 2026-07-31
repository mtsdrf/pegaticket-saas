<?php

namespace App\Models\Pdv;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PIN de operador por tenant (roadmap A4, item 15). Ver migration
 * create_user_pins_table para o porquê de tabela própria (não coluna em
 * `users`) e do hash determinístico.
 */
class UserPin extends BaseModel
{
    protected $table = 'user_pins';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'pin_hash',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'user_id',
        'pin_hash',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
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
