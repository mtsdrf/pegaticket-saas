<?php

namespace App\Services\Route;

use App\Models\Client\Client;
use App\Models\Location\Endereco;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rotas de entrega/cobrança (Fase 3.2 do roadmap) — leitura pura dos
 * "candidatos a parada" do dia escolhido, agrupados por Client. Sem
 * tabela própria (não persiste itinerário), sem DTO/Event (não é
 * mutação de domínio). Otimização de rota (OSRM) é 100% frontend.
 */
class RouteCandidateService
{
    public function candidates(int $tenantId, string $type, string $date): array
    {
        $stops = $type === 'collection'
            ? $this->collectionStops($tenantId, $date)
            : $this->deliveryStops($tenantId, $date);

        return [
            'type' => $type,
            'date' => $date,
            'stops' => $stops,
        ];
    }

    /**
     * Sem filtro de `is_delivered` de propósito: a tela de rota precisa
     * conseguir desfazer uma entrega marcada por engano, então uma parada
     * já entregue no dia continua aparecendo (com o estado refletido) em
     * vez de sumir da lista. Como o filtro é por `expected_delivery_date`
     * exato (não um intervalo pra trás), isso não acumula histórico velho
     * — só o dia escolhido.
     */
    private function deliveryStops(int $tenantId, string $date): array
    {
        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereDate('expected_delivery_date', $date)
            ->with($this->eagerLoads())
            ->get();

        $grouped = $orders->groupBy('client_id');

        $stops = $grouped->map(function (Collection $clientOrders) {
            /** @var Client $client */
            $client = $clientOrders->first()->client;

            $orders = $clientOrders
                ->map(fn(Order $order) => [
                    'uuid' => $order->uuid,
                    'total_amount' => $order->total_amount,
                    'is_paid' => $order->is_paid,
                    'is_delivered' => $order->is_delivered,
                    'is_installment' => $order->is_installment,
                    'expected_delivery_date' => optional($order->expected_delivery_date)->format('Y-m-d'),
                ])
                ->values()
                ->all();

            return $this->buildStop($client, $orders, []);
        });

        return $this->sortStops($stops);
    }

    /**
     * `due_date <= date` é um intervalo pra trás (inclui atrasadas de
     * qualquer época) — diferente de `deliveryStops()`, não dá pra
     * simplesmente tirar o filtro de pago, senão toda parcela paga há
     * meses reaparece na lista. Em vez disso: mantém parcelas ainda
     * abertas OU pagas justamente NO dia escolhido (`paid_at` = `date`) —
     * dá pra desfazer um pagamento marcado por engano hoje, sem
     * ressuscitar histórico antigo.
     */
    private function collectionStops(int $tenantId, string $date): array
    {
        $filterDate = Carbon::parse($date)->startOfDay();

        $installments = OrderInstallment::query()
            ->where('tenant_id', $tenantId)
            ->where(fn($q) => $q->where('is_paid', false)->orWhereDate('paid_at', $date))
            ->whereDate('due_date', '<=', $date)
            ->whereHas('order', fn($q) => $q->whereNull('cancelled_at'))
            ->with(array_map(fn($path) => "order.$path", ['client.endereco.cidade', 'client.endereco.bairro', 'client.diaIdeal', 'client.periodoIdeal']))
            ->get();

        $grouped = $installments->groupBy(fn(OrderInstallment $installment) => $installment->order->client_id);

        $stops = $grouped->map(function (Collection $clientInstallments) use ($filterDate) {
            /** @var Client $client */
            $client = $clientInstallments->first()->order->client;

            $installments = $clientInstallments
                ->map(fn(OrderInstallment $installment) => [
                    'uuid' => $installment->uuid,
                    'order_uuid' => $installment->order->uuid,
                    'amount' => $installment->amount,
                    'due_date' => optional($installment->due_date)->format('Y-m-d'),
                    'is_paid' => $installment->is_paid,
                    'is_overdue' => $installment->due_date !== null && $installment->due_date->lt($filterDate),
                ])
                ->values()
                ->all();

            return $this->buildStop($client, [], $installments);
        });

        return $this->sortStops($stops);
    }

    private function buildStop(Client $client, array $orders, array $installments): array
    {
        return [
            'client_uuid' => $client->uuid,
            'client_name' => $client->name,
            'phone_primary' => $client->phone_primary,
            'dia_ideal_name' => $client->diaIdeal->name ?? null,
            'periodo_ideal_name' => $client->periodoIdeal->name ?? null,
            'endereco' => $this->formatEndereco($client->endereco),
            'orders' => $orders,
            'installments' => $installments,
        ];
    }

    private function formatEndereco(?Endereco $endereco): ?array
    {
        if (!$endereco) {
            return null;
        }

        return [
            'logradouro' => $endereco->logradouro,
            'numero' => $endereco->numero,
            'bairro_name' => $endereco->bairro->name ?? null,
            'cidade_name' => $endereco->cidade->name ?? null,
            'lat' => $endereco->lat,
            'lng' => $endereco->lng,
            'geocode_status' => $endereco->geocode_status,
        ];
    }

    private function eagerLoads(): array
    {
        return ['client.endereco.cidade', 'client.endereco.bairro', 'client.diaIdeal', 'client.periodoIdeal'];
    }

    private function sortStops(Collection $stops): array
    {
        return $stops
            ->sortBy('client_name', SORT_STRING | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
