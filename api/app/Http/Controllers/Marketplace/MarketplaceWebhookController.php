<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Services\APIResponse;
use App\Services\Logging\ApplicationLogger;
use App\Services\Marketplace\IfoodWebhookService;
use App\Services\Marketplace\MarketplaceIntegrationService;
use Illuminate\Http\Request;

class MarketplaceWebhookController extends Controller
{
    public function __construct(
        private MarketplaceIntegrationService $service,
        private IfoodWebhookService $ifoodWebhookService,
    )
    {
    }

    public function ifood(Request $request, MarketplaceIntegration $marketplaceIntegration)
    {
        try {
            $payload = $request->all();
            $rawBody = (string) $request->getContent();
            $signature = $request->header('X-IFood-Signature');

            if (!$this->ifoodWebhookService->hasValidSignature($marketplaceIntegration, $rawBody, $signature)) {
                return APIResponse::error(
                    __('messages.marketplace.invalid_webhook_signature'),
                    401,
                    'MARKETPLACE_INVALID_SIGNATURE'
                );
            }

            if ($this->ifoodWebhookService->isKeepAlivePayload($payload)) {
                return response()->json(
                    $this->ifoodWebhookService->presenceResponse($marketplaceIntegration, is_array($payload) ? $payload : []),
                    202
                );
            }

            $result = $this->service->receiveWebhook(
                $marketplaceIntegration,
                $payload
            );

            return APIResponse::success([
                'processed' => $result['processed'],
            ], __('messages.marketplace.webhook_received'));
        } catch (\Throwable $e) {
            ApplicationLogger::error('Falha ao processar webhook de marketplace', [
                'provider' => $marketplaceIntegration->provider,
                'integration_uuid' => $marketplaceIntegration->uuid,
                'error' => $e->getMessage(),
            ]);

            return APIResponse::error(
                __('messages.marketplace.webhook_processing_failed'),
                422,
                'MARKETPLACE_WEBHOOK_FAILED'
            );
        }
    }
}
