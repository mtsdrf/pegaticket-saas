<?php

namespace App\Services\Marketplace;

use App\Contracts\Marketplace\MarketplaceProviderInterface;
use App\Enums\Marketplace\MarketplaceProvider;
use App\Exceptions\Marketplace\MarketplaceIntegrationException;

class MarketplaceProviderRegistry
{
    public function __construct(
        private IfoodMarketplaceProvider $ifoodMarketplaceProvider,
    ) {
    }

    public function for(string $provider): MarketplaceProviderInterface
    {
        return match ($provider) {
            MarketplaceProvider::Ifood->value => $this->ifoodMarketplaceProvider,
            default => throw new MarketplaceIntegrationException(__('messages.marketplace.provider_not_supported')),
        };
    }
}
