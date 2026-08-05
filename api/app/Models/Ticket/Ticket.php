<?php

namespace App\Models\Ticket;

use App\Models\BaseModel;
use App\Models\Event\TicketType;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use App\Models\Venue\Seat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Ingresso digital emitido (spec 5.15). Nunca criado via formulário — só
 * por TicketIssuanceService quando a Sale é confirmada/paga. `code`/
 * `qr_token` são gerados em booted() se ausentes, com verificação de
 * unicidade (colisão extremamente improvável, mas checada por segurança).
 */
class Ticket extends BaseModel
{
    protected $table = 'tickets';

    /**
     * Alfabeto sem 0/O/1/I — evita ambiguidade visual quando o comprador
     * precisa digitar/ler o código em voz alta.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'tenant_id',
        'sale_item_id',
        'ticket_type_id',
        'seat_id',
        'code',
        'qr_token',
        'attendee_name',
        'attendee_document',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'sale_item_id',
        'ticket_type_id',
        'seat_id',
        // qr_token nunca serializado em resposta padrão — só exposto via
        // TicketResource::withQrToken() explícito (dono do ingresso/staff
        // com permissão de reenvio), nunca em listagem.
        'qr_token',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Ticket $ticket) {
            if (empty($ticket->code)) {
                $ticket->code = self::generateUniqueCode();
            }

            if (empty($ticket->qr_token)) {
                $ticket->qr_token = self::generateUniqueQrToken();
            }
        });
    }

    /**
     * Titularidade/transferência (roadmap Fase 4): troca o participante e
     * invalida o code/qr_token antigo (evita que a pessoa anterior ainda
     * consiga usar um QR já compartilhado/salvo).
     */
    public function rotateAccessCredentials(): void
    {
        $this->code = self::generateUniqueCode();
        $this->qr_token = self::generateUniqueQrToken();
    }

    private static function generateUniqueCode(): string
    {
        do {
            $code = substr(str_shuffle(str_repeat(self::CODE_ALPHABET, 2)), 0, 8);
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::withTrashed()->where('qr_token', $token)->exists());

        return $token;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TicketCheckin::class);
    }

    /**
     * Anúncios de revenda oficial verificada (roadmap Fase 4) —
     * historicamente pode ter mais de um (revendido, cancelado, revendido
     * de novo), mas só um `listado` por vez (garantido em
     * TicketResaleService::create()).
     */
    public function resaleListings(): HasMany
    {
        return $this->hasMany(TicketResaleListing::class);
    }
}
