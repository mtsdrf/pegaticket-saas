<?php

namespace App\Models\Accounting;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Identidade global do escritório de contabilidade (roadmap 2C). NÃO estende
 * BaseModel de propósito — mesmo desvio de FinalCustomer (identidade global
 * sem tenant, sem ator staff que a cria). Tem `uuid` público via HasUuid e
 * implementa JWTSubject para o JWT manual dedicado do contador
 * (App\Services\Auth\AccountingJWTService).
 */
class AccountingOffice extends Model implements JWTSubject
{
    use HasUuid;

    protected $table = 'accounting_offices';

    protected $fillable = [
        'uuid',
        'cnpj',
        'company_name',
        'responsible_name',
        'email',
        'password_hash',
        'totp_secret',
        'totp_enabled_at',
    ];

    protected $hidden = [
        'id',
        'password_hash',
        'totp_secret',
    ];

    protected $casts = [
        'totp_enabled_at' => 'datetime',
        // Semente TOTP criptografada em repouso (cast nativo do Eloquent).
        // O acesso via atributo (`$office->totp_secret`) devolve o valor
        // decriptado transparentemente — google2fa->verifyKey continua
        // funcionando sem mudança. NOTA: registros gravados em texto puro
        // ANTES desta mudança falham ao decriptar; como a feature do
        // contador ainda não foi para produção, não há linha legada a
        // migrar (se houvesse, exigiria re-cadastro do 2FA).
        'totp_secret' => 'encrypted',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(AccountingOfficeTenant::class);
    }

    public function isTotpEnabled(): bool
    {
        return $this->totp_enabled_at !== null;
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
