<?php

namespace App\Models\FinalCustomer;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Identidade global do cliente final (consumidor que compra nas lojas),
 * distinta de App\Models\User\User (usuário STAFF/lojista). NÃO estende
 * BaseModel de propósito — ver migration correspondente. Ainda assim tem
 * `uuid` próprio (identificador público) via HasUuid.
 */
class FinalCustomer extends Model implements JWTSubject
{
    use HasUuid;

    protected $table = 'final_customers';

    protected $fillable = [
        'uuid',
        'name',
        'last_name',
        'email',
    ];

    protected $hidden = [
        'id',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(FinalCustomerTenantLink::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['uuid' => $this->uuid];
    }
}
