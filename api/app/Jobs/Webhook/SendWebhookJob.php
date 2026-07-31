<?php

namespace App\Jobs\Webhook;

use App\Exceptions\WebhookDeliveryFailedException;
use App\Models\Webhook\WebhookDelivery;
use App\Models\Webhook\WebhookSubscription;
use App\Services\Logging\ApplicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * POST assinado (HMAC-SHA256) para a URL cadastrada pelo tenant. Fila
 * dedicada `webhooks` (não a `default`, pra não competir com outros jobs
 * do projeto) — REQUER um worker processando essa fila em produção
 * (`queue:work --queue=webhooks,default` ou equivalente). Pendência já
 * documentada e aceita (mesmo padrão de GeocodeEnderecoJob): não existe
 * worker permanente configurado no servidor real ainda (Hostinger,
 * shared hosting) — ver .claude/memory/architecture-decisions.md.
 *
 * Recebe só o subscriptionId (não o Model) pelo mesmo motivo do
 * GeocodeEnderecoJob: evita serializar um snapshot desatualizado do
 * secret/url na fila caso a subscription seja editada entre o dispatch e
 * a execução.
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 600, 1800];

    public int $timeout = 15;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly string $deliveryUuid,
    ) {
    }

    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);

        if (!$subscription || !$subscription->is_active) {
            return;
        }

        $body = json_encode([
            'id' => $this->deliveryUuid,
            'event' => $this->eventType,
            'occurred_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $body, $subscription->secret);

        $status = null;
        $success = false;
        $error = null;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Maskats-Event' => $this->eventType,
                'X-Maskats-Delivery' => $this->deliveryUuid,
                'X-Maskats-Signature' => 'sha256=' . $signature,
            ])
                ->timeout(5)
                ->withBody($body, 'application/json')
                ->post($subscription->url);

            $status = $response->status();
            $success = $response->successful();

            if (!$success) {
                $error = 'HTTP ' . $status;
            }
        } catch (\Throwable $e) {
            $error = substr($e->getMessage(), 0, 500);
        }

        WebhookDelivery::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $subscription->tenant_id,
            'webhook_subscription_id' => $subscription->id,
            'event_type' => $this->eventType,
            'payload' => json_decode($body, true),
            'response_status' => $status,
            'success' => $success,
            'attempt' => $this->attempts(),
            'error' => $error,
            'attempted_at' => now(),
        ]);

        if (!$success) {
            throw new WebhookDeliveryFailedException($error ?? 'unknown_error');
        }
    }

    /**
     * $tries esgotadas — o WebhookDelivery de cada tentativa já ficou
     * registrado no handle(); aqui só loga pra observabilidade (mesmo
     * padrão de GeocodeEnderecoJob::failed()). Nunca loga secret/URL
     * completa da subscription (ver security-standards.md).
     */
    public function failed(\Throwable $e): void
    {
        ApplicationLogger::warning('Falha ao entregar webhook (esgotou tentativas)', [
            'subscription_id' => $this->subscriptionId,
            'event_type' => $this->eventType,
            'delivery_uuid' => $this->deliveryUuid,
            'exception' => $e->getMessage(),
        ]);
    }
}
