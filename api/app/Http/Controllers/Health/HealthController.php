<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Health\HealthCheckService;

class HealthController extends Controller
{
    public function __construct(
        private HealthCheckService $service
    ) {
    }

    /**
     * Endpoint público de monitoramento (roadmap A1.1) — sem jwt/tenant/perm,
     * só throttle. Shape de resposta próprio (não usa APIResponse::success
     * pro corpo de sucesso porque um monitor externo, ex. UptimeRobot,
     * espera {"status":"ok","checks":{...}} direto na raiz, não embrulhado
     * em {success,message,data,meta}); erro (503) continua usando
     * APIResponse::error para manter o contrato de erro do projeto.
     */
    public function show()
    {
        $result = $this->service->run();

        if (!$result['healthy']) {
            return APIResponse::error(
                __('messages.health.unhealthy'),
                503,
                'SERVICE_UNAVAILABLE',
                $result['checks']
            );
        }

        return response()->json([
            'status' => 'ok',
            'checks' => $result['checks'],
        ], 200);
    }
}
