<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class PlatformFinanceSettings extends BaseModel
{
    protected $table = 'platform_finance_settings';

    protected $fillable = [
        'platform_fee_fixed_amount',
        'default_settlement_offset_days',
        'settlement_reference',
        'split_custody_enabled',
        'extra_reserve_enabled',
        'extra_reserve_percentage',
        'extra_reserve_release_offset_days',
        'pagbank_primary_account_id',
    ];

    protected $casts = [
        'platform_fee_fixed_amount' => 'decimal:2',
        'default_settlement_offset_days' => 'integer',
        'split_custody_enabled' => 'boolean',
        'extra_reserve_enabled' => 'boolean',
        'extra_reserve_percentage' => 'decimal:2',
        'extra_reserve_release_offset_days' => 'integer',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}
