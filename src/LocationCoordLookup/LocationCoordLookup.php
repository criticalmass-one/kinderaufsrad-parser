<?php declare(strict_types=1);

namespace App\LocationCoordLookup;

use App\Model\Ride;
use maxh\Nominatim\Nominatim;
use maxh\Nominatim\QueryInterface;

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

        $search = $this->buildSearch($ride);

        $resultList = $this->lookup($search);

        if (1 === count($resultList)) {
            $result = array_pop($resultList);

            $ride = $this->assignPropertyValues($ride, $result);
        }

        return $ride;
    }

    protected function buildSearch(Ride $ride): QueryInterface
    {
        $search = $this->nominatim->newSearch()
            ->country('Germany')
            ->city($ride->getCityName())
            ->query($ride->getLocation())
            ->addressDetails()
            ->limit(1);

        return $search;
    }

    protected function lookup(QueryInterface $search): array
    {
        return $this->nominatim->find($search);
    }

    protected function assignPropertyValues(Ride $ride, array $result): Ride
    {
        if (array_key_exists('lat', $result) && array_key_exists('lon', $result)) {
            $ride
                ->setLatitude((float) $result['lat'])
                ->setLongitude((float) $result['lon']);
        }

        return $ride;
    }
}
