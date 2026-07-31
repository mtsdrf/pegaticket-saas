<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceIntegration extends BaseModel
{
    protected $table = 'marketplace_integrations';

    protected $fillable = [
        'tenant_id',
        'provider',
        'name',
        'environment',
        'auth_mode',
        'status',
        'is_active',
        'client_id',
        'client_secret',
        'authorization_code',
        'access_token',
        'refresh_token',
        'merchant_id',
        'webhook_url',
        'polling_merchant_ids',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'last_connected_at',
        'last_synced_at',
        'last_polled_at',
        'last_error_at',
        'last_error_message',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'client_secret' => 'encrypted',
        'authorization_code' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_polled_at' => 'datetime',
        'last_error_at' => 'datetime',
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'client_secret',
        'authorization_code',
        'access_token',
        'refresh_token',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(MarketplaceMerchant::class, 'integration_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class, 'integration_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'integration_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MarketplaceAction::class, 'integration_id');
    }

    public function catalogSyncs(): HasMany
    {
        return $this->hasMany(MarketplaceCatalogSync::class, 'integration_id');
    }

    public function catalogMappings(): HasMany
    {
        return $this->hasMany(MarketplaceCatalogMapping::class, 'integration_id');
    }

    /**
     * @return list<string>
     */
    public function pollingMerchantIdsList(): array
    {
        if (!$this->polling_merchant_ids) {
            return [];
        }

        return collect(explode(',', (string) $this->polling_merchant_ids))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values()
            ->all();
    }
}
