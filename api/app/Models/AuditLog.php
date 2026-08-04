<?php

namespace App\Models;

use App\Support\AuthContext;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'route',
        'method',
        'ip',
        'user_agent',
        'old_values',
        'new_values',
        'meta',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    /**
     * Registra auditoria técnica ou semântica.
     */
    public static function record(
        string $event,
        ?Model $model = null,
        array $meta = [],
        ?int $actorId = null
    ): void {
        $old = null;
        $new = null;

        if ($model) {
            switch ($event) {
                case 'created':
                    $new = $model->getAttributes();
                    break;

                case 'updated':
                    $old = $model->getOriginal();
                    $new = $model->getAttributes();
                    break;

                case 'deleted':
                    $old = $model->getOriginal();
                    $new = $model->getAttributes();
                    break;

                default:
                    $new = $model->getAttributes();
            }

            // Remover campos sensíveis (mesma lista também filtra colunas
            // binárias grandes, ex. avatar_data — nunca são "secretas", mas
            // json_encode() de bytes crus pode falhar/corromper old_values e
            // infla audit_logs com megabytes por upload; reaproveita o
            // mesmo mecanismo de array_diff_key).
            $sensitive = [
                'password',
                'remember_token',
                'token',
                'token_hash',
                'refresh_token',
                'pending_email_token_hash',
                'password_reset_token_hash',
                'totp_secret',
                'payment_pix_key',
                'pagbank_access_token',
                'avatar_data',
                'pin_hash',
                'key_hash',
                'secret',
                'client_secret',
                'authorization_code',
                'access_token',
            ];

            if (is_array($old)) {
                $old = array_diff_key($old, array_flip($sensitive));
            }

            if (is_array($new)) {
                $new = array_diff_key($new, array_flip($sensitive));
            }
        }

        self::create([
            'user_id' => $actorId ?? AuthContext::safeUserId(),
            'event' => $event,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'route' => Request::path(),
            'method' => Request::method(),
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'old_values' => $old,
            'new_values' => $new,
            'meta' => $meta,
        ]);
    }

    /**
     * Registra auditoria cujo ATOR NÃO é um User (ex.: identidade externa
     * autenticada por um JWT próprio, fora de `users`). `user_id` fica
     * SEMPRE null (nunca cai no fallback AuthContext::safeUserId(), que
     * resolveria o subject do JWT ativo e gravaria um id que não é de
     * `users`, corrompendo a FK/relacionamento `user()`). A identidade real
     * do ator vai em `meta`, decidido pelo chamador.
     */
    public static function recordForNonUser(string $event, array $meta = []): void
    {
        self::create([
            'user_id' => null,
            'event' => $event,
            'auditable_type' => null,
            'auditable_id' => null,
            'route' => Request::path(),
            'method' => Request::method(),
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'old_values' => null,
            'new_values' => null,
            'meta' => $meta,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
