<?php

namespace App\Enums\Marketplace;

enum MarketplaceEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';
}
