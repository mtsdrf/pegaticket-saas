<?php

return [
    'default' => 'pt_BR',

    'documentations' => [
        'pt_BR' => [
            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', env('APP_URL')),
            ],
            'api' => [
                'title' => 'Documentação da API Empresarial',
            ],
            'routes' => [
                'api' => 'api/documentation',              // mantém pt_BR sem sufixo (ok)
                'docs' => 'docs/pt_BR',                    // ✅ precisa ser único
                'oauth2_callback' => 'api/oauth2-callback/pt_BR', // ✅ precisa ser único
            ],
            'paths' => [
                'docs_json' => 'api-docs-pt_BR.json',
                'docs_yaml' => 'api-docs-pt_BR.yaml',
                'annotations' => [
                    base_path('resources/docs/PtBR'),
                ],
            ],
        ],

        'en' => [
            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', env('APP_URL')),
            ],
            'api' => [
                'title' => 'Enterprise API Documentation',
            ],
            'routes' => [
                'api' => 'api/documentation/en',
                'docs' => 'docs/en',                       // ✅ precisa ser único
                'oauth2_callback' => 'api/oauth2-callback/en', // ✅ precisa ser único
            ],
            'paths' => [
                'docs_json' => 'api-docs-en.json',
                'docs_yaml' => 'api-docs-en.yaml',
                'annotations' => [
                    base_path('resources/docs/En'),
                ],
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],
        'additional_config_url' => null,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'paths' => [
            'docs' => storage_path('api-docs'),
            'views' => base_path('resources/views/vendor/l5-swagger'),
            'base' => env('L5_SWAGGER_BASE_PATH', null),
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
            'excludes' => [],
        ],
        'scanOptions' => [
            'default_processors_configuration' => [],
            'open_api_spec_version' => \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION,
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => false,
        'proxy' => false,
        'validator_url' => null,
        'ui' => [
            'display' => [
                'dark_mode' => env('L5_SWAGGER_UI_DARK_MODE', false),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'list'),
                'filter' => true,
            ],
            'authorization' => [
                'persist_authorization' => true,
            ],
        ],
    ],
];