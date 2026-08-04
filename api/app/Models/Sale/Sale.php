<?php

namespace App\Models\Sale;

use App\Models\Affiliate\Affiliate;
use App\Models\BaseModel;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Finance\Receivable;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\SaleRating;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class Sale extends BaseModel
{
    public const LEGACY_ORIGIN_MAP = [
        'counter' => 'staff',
    ];

    protected $table = 'sales';

    protected $fillable = [
        'tenant_id',
        'final_customer_id',
        'codigo',
        'is_installment',
        'total_amount',
        'service_fee',
        'coupon_id',
        'discount_amount',
        'paid_amount',
        'is_paid',
        'paid_at',
        'reminder_sent_at',
        'due_date',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'payment_method',
        'needs_change',
        'change_for_amount',
        'status',
        'status_before_cancellation_request',
        'origin',
        'purchaser_ip',
        'operated_by',
        'client_sale_uuid',
        'affiliate_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'recompra_nudge_sent_at',
        'risk_flagged',
        'risk_reason',
    ];

    protected $casts = [
        'is_installment' => 'boolean',
        'total_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'needs_change' => 'boolean',
        'change_for_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'recompra_nudge_sent_at' => 'datetime',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
        'risk_flagged' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'final_customer_id',
        'coupon_id',
        'operated_by',
        'client_sale_uuid',
        'affiliate_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Invariante: is_paid=true sempre tem que vir com paid_at preenchido.
     * Todo fluxo atual (create() com venda manual já paga, cascata de
     * payInstallment(), reconciliação de webhook) já seta a data — este
     * guard é a rede de segurança pra qualquer escrita futura (import,
     * tinker, feature nova) que marque o flag sem a data. Achado real: o
     * import legado (2026-07) podia gravar is_paid=true com data nula
     * quando o campo de data de origem vinha vazio/inválido (ver
     * ImportLegacyJsQueijosCommand, sanitizeDate() retornando null) — 10
     * vendas sem paid_at encontrados em produção e corrigidos
     * manualmente; este guard evita repetir o mesmo padrão de novo (regra
     * de não repetição de erro).
     */
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Sale $order) {
            if ($order->codigo || ! $order->tenant_id) {
                return;
            }

            // Rede de segurança para qualquer criação que passe por
            // `Sale::create()` direto (fora de SaleService::create()):
            // mantém a mesma convenção por tenant (999 -> 1000) e evita
            // vendas novos sem código de exibição.
            DB::table('tenants')->where('id', $order->tenant_id)->increment('next_sale_code');
            $order->codigo = (string) DB::table('tenants')->where('id', $order->tenant_id)->value('next_sale_code');
        });

        static::saving(function (Sale $order) {
            if ($order->is_paid && ! $order->paid_at) {
                $order->paid_at = now();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function normalizeOrigin(?string $origin): string
    {
        if ($origin === null || $origin === '') {
            return 'staff';
        }

        return self::LEGACY_ORIGIN_MAP[$origin] ?? $origin;
    }

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class, 'final_customer_id');
    }

    /**
     * Resolve o FinalCustomerTenantLink (dados por-tenant: telefone,
     * endereço, documento) do MESMO tenant desta venda.
     */
    public function finalCustomerLink()
    {
        return $this->belongsTo(FinalCustomerTenantLink::class, 'final_customer_id', 'final_customer_id')
            ->where('tenant_id', $this->tenant_id);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * Operador identificado por PIN que concluiu a operação — distinto de
     * created_by (usuário do JWT da sessão).
     */
    public function operator()
    {
        return $this->belongsTo(User::class, 'operated_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function installments()
    {
        return $this->hasMany(SaleInstallment::class, 'sale_id');
    }

    public function rating()
    {
        return $this->hasOne(SaleRating::class, 'sale_id');
    }

    /**
     * Cobranças de pagamento da venda (roadmap 2A) — recebimento do tenant
     * (cliente final → tenant), via a mesma tabela polimórfica `payments`
     * usada pela cobrança de assinatura da PegaTicket.
     */
    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment()
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class);
    }
}
