<?php

namespace App\Services\Communication;

class BrandedEmailLayoutRenderer
{
    public function wrap(string $html, array $options = []): string
    {
        if ($this->isFullDocument($html)) {
            return $html;
        }

        return view('emails.layouts.base', [
            'preheader' => $options['preheader'] ?? null,
            'headline' => $options['headline'] ?? null,
            'subheadline' => $options['subheadline'] ?? null,
            'footerNote' => $options['footer_note'] ?? null,
            'contentHtml' => $html,
        ])->render();
    }

    private function isFullDocument(string $html): bool
    {
        $normalized = strtolower($html);

        return str_contains($normalized, '<!doctype')
            || str_contains($normalized, '<html')
            || str_contains($normalized, '<body');
    }
}
