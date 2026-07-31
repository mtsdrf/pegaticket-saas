<?php

if (!function_exists('portal_customer')) {
    /**
     * Cliente final autenticado na requisição atual (bind manual feito por
     * App\Http\Middleware\CustomerJwtAccessMiddleware), mesmo espírito de
     * tenant()/tenant_id() em app/Support/tenant.php.
     */
    function portal_customer(): ?\App\Models\FinalCustomer\FinalCustomer
    {
        return app()->bound('portal_customer') ? app('portal_customer') : null;
    }
}
