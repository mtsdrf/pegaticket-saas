<?php

namespace App\Models;

use App\Support\AuthContext;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class BaseAuthenticatable extends Authenticatable
{
    use SoftDeletes, HasUuid, Auditable;

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $uid = AuthContext::safeUserId();
            if ($uid) {
                $model->created_by ??= $uid;
                $model->updated_by ??= $uid;
            }
        });

        static::updating(function ($model) {
            $uid = AuthContext::safeUserId();
            if ($uid)
                $model->updated_by = $uid;
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
