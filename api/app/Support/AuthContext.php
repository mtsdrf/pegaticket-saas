<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class AuthContext
{
    public static function safeUserId(): ?int
    {
        try {
            $id = Auth::id();

            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
