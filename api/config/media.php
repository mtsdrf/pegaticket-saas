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
        'product' => env(
            'MEDIA_PRODUCTS_DISK',
            env('R2_ENABLED', false) ? 'r2_products' : 'public'
        ),
        'tenant' => env(
            'MEDIA_TENANTS_DISK',
            env('R2_ENABLED', false) ? 'r2_tenants' : 'public'
        ),
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
