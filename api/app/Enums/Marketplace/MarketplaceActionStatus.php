<?php

namespace App\Enums\Marketplace;

enum MarketplaceActionStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
