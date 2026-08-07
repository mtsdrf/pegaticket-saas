<?php

namespace App\Services\Ticket;

use App\Models\Sale\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TicketPdfService
{
    /**
     * @param  Collection<int, \App\Models\Ticket\Ticket>  $tickets
     * @return array{content: string, filename: string}
     */
    public function generateForSale(Sale $sale, Collection $tickets): array
    {
        $sale->loadMissing('tenant', 'finalCustomer');
        $tickets->loadMissing('ticketType.event', 'ticketType.session', 'seat', 'saleItem.sale');

        $pdf = Pdf::loadView('tickets.pdf', [
            'sale' => $sale,
            'tickets' => $tickets,
            'tenantName' => $sale->tenant?->name,
            'generatedAt' => now(),
            'trackingUrl' => rtrim((string) config('app.frontend_url'), '/').'/compra/'.$sale->uuid,
            'logoDataUri' => $this->logoDataUri(),
        ]);

        return [
            'content' => $pdf->output(),
            'filename' => 'ingressos-'.$this->normalizedFileSegment((string) $sale->codigo).'.pdf',
        ];
    }

    public function publicDownloadUrl(Sale $sale): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/rastreio/'.$sale->uuid.'/ingressos.pdf';
    }

    /**
     * @return Collection<int, \App\Models\Ticket\Ticket>
     */
    public function issuedTicketsForSale(Sale $sale): Collection
    {
        return \App\Models\Ticket\Ticket::query()
            ->where('tenant_id', $sale->tenant_id)
            ->whereHas('saleItem', fn ($query) => $query->where('sale_id', $sale->id))
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    private function normalizedFileSegment(string $value): string
    {
        $normalized = Str::of($value)->lower()->slug('-')->value();

        return $normalized !== '' ? $normalized : 'venda';
    }

    private function logoDataUri(): ?string
    {
        $candidates = [
            base_path('../web/public/logo.png'),
            base_path('../web/public/logo.svg'),
            public_path('logo.png'),
            public_path('logo.svg'),
        ];

        foreach ($candidates as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $mime = str_ends_with($path, '.svg') ? 'image/svg+xml' : 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return null;
    }
}
