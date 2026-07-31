<?php

if (!function_exists('tenant')) {
    function tenant()
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}

if (!function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app()->bound('tenant_id') ? app('tenant_id') : null;
    }
}