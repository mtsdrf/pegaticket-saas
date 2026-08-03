<?php

namespace App\Models\GuestList;

use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuestListEntry extends BaseModel
{
    protected $table = 'guest_list_entries';

    protected $fillable = [
        'tenant_id',
        'guest_list_id',
        'sale_id',
        'name',
        'email',
        'document',
        'token',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'guest_list_id',
        'sale_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (GuestListEntry $entry) {
            if (empty($entry->token)) {
                $entry->token = self::generateUniqueToken();
            }
        });
    }

    private static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::withTrashed()->where('token', $token)->exists());

        return $token;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function guestList(): BelongsTo
    {
        return $this->belongsTo(GuestList::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
