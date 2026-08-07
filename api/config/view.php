<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Blade e demais engines de view procuram templates nesses caminhos.
    | O projeto segue o padrão Laravel em `resources/views`.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | O CI limpa o cache de configuração antes dos testes; sem esse arquivo,
    | `view.compiled` fica nulo e qualquer renderização Blade falha com
    | "Please provide a valid cache path.".
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
