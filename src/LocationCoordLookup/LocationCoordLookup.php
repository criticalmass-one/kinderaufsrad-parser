<?php declare(strict_types=1);

namespace App\LocationCoordLookup;

use App\Model\Ride;
use maxh\Nominatim\Nominatim;

class LocationCoordLookup implements LocationCoordLookupInterface
{
    protected Nominatim $nominatim;

    public function __construct()
    {
        $this->nominatim = new Nominatim('http://nominatim.openstreetmap.org/');
    }

    public function lookupCoordsForRideLocation(Ride $ride): Ride
    {
        if (!$ride->getCityName() || !$ride->getLocation()) {
            return $ride;
        }

        $search = $this->nominatim->newSearch()
            ->country('Germany')
            ->city($ride->getCityName())
            ->query($ride->getLocation())
            ->addressDetails()
            ->limit(1);

        $resultList = $this->nominatim->find($search);

        if (1 === count($resultList)) {
            $result = array_pop($resultList);

            $ride
                ->setLatitude((float) $result['lat'])
                ->setLongitude((float) $result['lon']);
        }

        return $ride;
    }
}
