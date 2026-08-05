<?php

namespace App\Models\Report;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura de resumo agendado do Home (roadmap A2) — e-mail próprio ou
 * customizado recebe diária/semanalmente os KPIs de
 * ReportService::indicators(). Ver App\Console\Commands\
 * SendScheduledReportSummariesCommand para o disparo.
 */
class ScheduledReportSubscription extends BaseModel
{
    protected $table = 'scheduled_report_subscriptions';

    protected $casts = [
        'last_sent_at' => 'datetime',
    ];

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCIES = [self::FREQUENCY_DAILY, self::FREQUENCY_WEEKLY];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Em dia com a frequência configurada — diária vence a cada 24h,
     * semanal a cada 7 dias, sempre a partir de `last_sent_at` (nunca
     * enviada = sempre devida).
     */
    protected function isDue(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->last_sent_at) {
                return true;
            }

            $intervalDays = $this->frequency === self::FREQUENCY_WEEKLY ? 7 : 1;

            return $this->last_sent_at->lte(now()->subDays($intervalDays));
        });
    }
}
