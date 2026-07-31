<?php

namespace App\Models\Order;

use App\Models\BaseModel;
use App\Models\Client\Client;
use App\Models\Stock\StockLocation;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\OrderRating;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;

class Order extends BaseModel
{
    protected $table = 'orders';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'stock_location_id',
        'codigo',
        'is_installment',
        'total_amount',
        'delivery_fee',
        'service_fee',
        'coupon_id',
        'discount_amount',
        'cashback_redeemed_amount',
        'paid_amount',
        'is_paid',
        'paid_at',
        'is_delivered',
        'delivered_at',
        'due_date',
        'expected_delivery_date',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'payment_method',
        'needs_change',
        'change_for_amount',
        'status',
        'status_before_cancellation_request',
        'origin',
        'fulfillment_type',
        'stock_reserved',
        'is_out_for_delivery',
        'out_for_delivery_at',
        'operated_by',
        'client_sale_uuid',
    ];

    protected $casts = [
        'is_installment' => 'boolean',
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'cashback_redeemed_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'needs_change' => 'boolean',
        'change_for_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'is_delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'due_date' => 'date',
        'expected_delivery_date' => 'date',
        'cancelled_at' => 'datetime',
        'stock_reserved' => 'boolean',
        'is_out_for_delivery' => 'boolean',
        'out_for_delivery_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'client_id',
        'stock_location_id',
        'coupon_id',
        'operated_by',
        'client_sale_uuid',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Invariante: is_delivered/is_paid=true sempre tem que vir com a data
     * correspondente. Todo fluxo atual (deliver()/pay()/create() com
     * markAsDelivered/markAsPaid) já seta a data — este guard é a rede de
     * segurança pra qualquer escrita futura (import, tinker, feature nova)
     * que marque o flag sem a data. Achado real: o import legado (2026-07)
     * podia gravar is_paid/is_delivered=true com data nula quando o campo de
     * data de origem vinha vazio/inválido (ver ImportLegacyJsQueijosCommand,
     * sanitizeDate() retornando null) — 4 pedidos sem delivered_at e 10 sem
     * paid_at encontrados em produção e corrigidos manualmente; este guard
     * evita repetir o mesmo padrão de novo (regra de não repetição de erro).
     */
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Order $order) {
            if ($order->codigo || !$order->tenant_id) {
                return;
            }

            // Rede de segurança para qualquer criação que passe por
            // `Order::create()` direto (fora de OrderService::create()):
            // mantém a mesma convenção por tenant (999 -> 1000) e evita
            // pedidos novos sem código de exibição.
            DB::table('tenants')->where('id', $order->tenant_id)->increment('next_order_code');
            $order->codigo = (string) DB::table('tenants')->where('id', $order->tenant_id)->value('next_order_code');
        });

        static::saving(function (Order $order) {
            if ($order->is_delivered && !$order->delivered_at) {
                $order->delivered_at = now();
            }

            if ($order->is_paid && !$order->paid_at) {
                $order->paid_at = now();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function stockLocation()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Operador identificado por PIN (roadmap A4, item 15) que bateu a venda
     * de PDV — distinto de created_by (usuário do JWT da sessão).
     */
    public function operator()
    {
        return $this->belongsTo(\App\Models\User\User::class, 'operated_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function installments()
    {
        return $this->hasMany(OrderInstallment::class);
    }

    public function rating()
    {
        return $this->hasOne(OrderRating::class);
    }

    /**
     * Cobranças de pagamento do pedido (roadmap 2A) — recebimento do tenant
     * (cliente final → tenant), via a mesma tabela polimórfica `payments`
     * usada pela cobrança de assinatura da PegaTicket.
     */
    public function payments()
    {
        return $this->morphMany(\App\Models\Subscription\Payment::class, 'payable');
    }
}
