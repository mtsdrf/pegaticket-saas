<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFinancialCloseoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event' => $this['event'],
            'closeout_status' => $this['closeout_status'],
            'totals' => $this['totals'],
            'settlements' => $this['settlements'],
            'adjustments' => $this['adjustments'],
            'receivables' => $this['receivables'],
            'generated_at' => $this['generated_at'],
        ];
    }
}
