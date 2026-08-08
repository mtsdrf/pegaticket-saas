<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

/**
 * Estado do fluxo OAuth Connect do PagBank por tenant (roadmap fase R2).
 * Tokens sempre criptografados em repouso via cast `encrypted` nativo do
 * Eloquent — nunca acessar/logar `access_token_encrypted`/
 * `refresh_token_encrypted` fora deste model/PagBankConnectService.
 */
class TenantPagBankConnection extends BaseModel
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_PENDING_CONNECTION = 'pending_connection';

    public const STATUS_PENDING_KYC = 'pending_kyc';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_ENABLED = 'enabled';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_DISABLED = 'disabled';

    // Caminho Account/Cadastro (R2.2, roadmap seção 9.4) — só usados por
    // registros com connection_type=CONNECTION_TYPE_CREATED_BY_PLATFORM.
    public const STATUS_STARTED = 'started';

    public const STATUS_PENDING_SUBMISSION = 'pending_submission';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ERROR = 'error';

    public const CONNECTION_TYPE_CONNECTED_EXISTING = 'connected_existing';

    public const CONNECTION_TYPE_CREATED_BY_PLATFORM = 'created_by_platform';

    public const ACCOUNT_PERSON_TYPE_PF = 'pf';

    public const ACCOUNT_PERSON_TYPE_PJ = 'pj';

    protected $table = 'tenant_pagbank_connections';

    protected $fillable = [
        'tenant_id',
        'provider',
        'connection_type',
        'account_person_type',
        'status',
        'provider_status',
        'account_id',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'document_encrypted',
        'token_expires_at',
        'scope',
        'connect_state',
        'connected_at',
        'verified_at',
        'submitted_at',
        'disconnected_at',
        'environment',
    ];

    protected $casts = [
        'access_token_encrypted' => 'encrypted',
        'refresh_token_encrypted' => 'encrypted',
        'document_encrypted' => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'verified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
        'document_encrypted',
        'connect_state',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    public function isCreatedByPlatform(): bool
    {
        return $this->connection_type === self::CONNECTION_TYPE_CREATED_BY_PLATFORM;
    }

    /**
     * Estados que representam uma solicitação em curso/finalizada com o
     * PagBank — usado por PagBankAccountService para impedir criação
     * duplicada de conta (idempotência, roadmap seção 9.5.2).
     */
    public function hasSubmittedAccountRequest(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_SUBMISSION,
            self::STATUS_SUBMITTED,
            self::STATUS_VERIFIED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_ENABLED,
        ], true);
    }

    /**
     * Estados "aguardando confirmação externa" — únicos em que a
     * sincronização periódica (roadmap R2.5, `SyncPagBankTenantAccountJob`)
     * faz sentido consultar o PagBank de novo. `pending_connection`/
     * `started` ficam de fora de propósito: resolvem via callback OAuth
     * (Connect) ou pela própria criação da conta (Account), não por
     * polling — sincronizar esses dois só adicionaria chamadas
     * desnecessárias sem transição possível via GET /accounts/{id}.
     * `not_configured`/`enabled`/`verified`/`restricted`/`disabled`/
     * `rejected`/`error` também ficam de fora: são estados finais (ou o
     * tenant nunca começou), sem mais nada para consultar.
     */
    public function needsPeriodicStatusSync(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_KYC,
            self::STATUS_UNDER_REVIEW,
        ], true);
    }
}
