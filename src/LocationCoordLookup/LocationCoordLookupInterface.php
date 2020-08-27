<?php declare(strict_types=1);

namespace App\LocationCoordLookup;

use App\Model\Ride;

interface LocationCoordLookupInterface
{
    public function lookupCoordsForRideLocation(Ride $ride): Ride;
}
