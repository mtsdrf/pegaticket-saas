<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media storage strategy
    |--------------------------------------------------------------------------
    |
    | "public" continua sendo o fallback local/Hostinger-friendly. Quando o
    | projeto estiver com R2 habilitado, os uploads públicos passam a usar
    | "r2_public" e os anexos privados podem usar "r2_private".
    |
    */
    'public_disks' => [
        'avatar' => env(
            'MEDIA_AVATARS_DISK',
            env('R2_ENABLED', false) ? 'r2_avatars' : 'public'
        ),
        'ticket_type' => env(
            'MEDIA_TICKET_TYPES_DISK',
            env('R2_ENABLED', false) ? 'r2_ticket_types' : 'public'
        ),
        'event' => env(
            'MEDIA_EVENTS_DISK',
            env('R2_ENABLED', false) ? 'r2_events' : 'public'
        ),
        'tenant' => env(
            'MEDIA_TENANTS_DISK',
            env('R2_ENABLED', false) ? 'r2_tenants' : 'public'
        ),
        'venue' => env(
            'MEDIA_VENUES_DISK',
            env('R2_ENABLED', false) ? 'r2_venues' : 'public'
        ),

        // Comprovante de estorno externo (spec 5.14) — documento
        // financeiro sensível, NUNCA deve ser servido por URL pública
        // direta. Sempre 'local' por padrão (storage/app/private, sem
        // symlink público), diferente de todos os outros mediaKeys acima
        // que alternam para r2_* quando R2_ENABLED=true — não existe (nem
        // deve existir sem decisão explícita) um disco r2 PRIVADO
        // configurado em filesystems.php ainda, então este mediaKey não
        // segue esse padrão condicional. Só acessível via
        // SaleRefundController::receipt(), autenticado + tenant + perm.
        'sale_refund' => env('MEDIA_SALE_REFUNDS_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public media delivery
    |--------------------------------------------------------------------------
    |
    | true: resources devolvem URL direta do disk público (ideal para cache/CDN)
    | false: resources mantêm proxy pela API quando o ativo já estiver em path
    |
    */
    'use_direct_public_urls' => env('MEDIA_USE_DIRECT_PUBLIC_URLS', true),

    'public_cache_seconds' => (int) env('MEDIA_PUBLIC_CACHE_SECONDS', 31536000),
];
