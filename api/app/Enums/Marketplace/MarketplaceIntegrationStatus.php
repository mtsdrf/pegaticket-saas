<?php

namespace App\Enums\Marketplace;

enum MarketplaceIntegrationStatus: string
{
    case Disconnected = 'disconnected';
    case Connected = 'connected';
    case Attention = 'attention';
}
