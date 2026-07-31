<?php

namespace App\Enums\Marketplace;

enum MarketplaceEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
