<?php

if (!function_exists('accounting_office')) {
    /**
     * Escritório de contabilidade autenticado na requisição atual (bind manual
     * feito por App\Http\Middleware\AccountingJwtAccessMiddleware), mesmo
     * espírito de tenant()/portal_customer().
     */
    function accounting_office(): ?\App\Models\Accounting\AccountingOffice
    {
        return app()->bound('accounting_office') ? app('accounting_office') : null;
    }
}
