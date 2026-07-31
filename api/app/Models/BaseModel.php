<?php

namespace App\Models;

use App\Support\AuthContext;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use SoftDeletes, HasUuid;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            $uid = AuthContext::safeUserId();
            if ($uid) {
                $model->created_by ??= $uid;
                $model->updated_by ??= $uid;
            }
        });

        static::updating(function ($model) {
            $uid = AuthContext::safeUserId();
            if ($uid) {
                $model->updated_by = $uid;
            }
        });

        static::deleting(function ($model) {
            $uid = AuthContext::safeUserId();
            if ($uid) {
                $model->deleted_by = $uid;
                $model->saveQuietly();
            }
        });
    }
}
