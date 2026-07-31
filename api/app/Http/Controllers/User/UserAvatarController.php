<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;

/**
 * Serve o avatar direto do banco (LONGBLOB) — rota 100% pública, sem
 * jwt/tenant/perm, mesmo espírito de /rastreio (route-model-binding via
 * uuid já resolve 404 pra usuário inexistente/soft-deletado). Sem
 * Service/Repository: leitura simples de um Model já resolvido, mesmo
 * precedente de OrderTrackingController. Ver architecture-decisions.md.
 */
class UserAvatarController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(User $user): Response
    {
        return $this->mediaStorage->publicMediaResponse(
            $user->avatar_path,
            $user->avatar_data,
            $user->avatar_mime,
            'avatar'
        );
    }
}
