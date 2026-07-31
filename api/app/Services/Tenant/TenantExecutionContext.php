<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;

class TenantExecutionContext
{
    /**
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $keys = ['tenant', 'tenant_id', 'tenant_uuid', 'tenant_user', 'tenant_role'];
        $previous = [];

        foreach ($keys as $key) {
            if (app()->bound($key)) {
                $previous[$key] = app($key);
            }
        }

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant_uuid', $tenant->uuid);

        foreach (['tenant_user', 'tenant_role'] as $key) {
            if (app()->bound($key)) {
                app()->forgetInstance($key);
            }
        }

        try {
            return $callback();
        } finally {
            foreach ($keys as $key) {
                if (array_key_exists($key, $previous)) {
                    app()->instance($key, $previous[$key]);
                } elseif (app()->bound($key)) {
                    app()->forgetInstance($key);
                }
            }
        }
    }
}
