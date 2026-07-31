<?php

namespace App\Models\Legal;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Log imutável de aceite. NÃO estende BaseModel (sem soft delete/autoria —
 * o "autor" é o próprio usuário que aceitou, gravado em user_id), só usa
 * HasUuid para o identificador público, mesmo desvio deliberado já usado
 * por FinalCustomerOtp/CouponRedemption.
 */
class LegalAcceptance extends Model
{
    use HasUuid;

    protected $table = 'legal_acceptances';

    protected $fillable = [
        'user_id',
        'legal_document_id',
        'accepted_at',
        'ip',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'legal_document_id',
    ];

    public function document()
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
