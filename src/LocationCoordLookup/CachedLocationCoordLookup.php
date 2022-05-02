<?php declare(strict_types=1);

namespace App\LocationCoordLookup;

use App\Model\Ride;
use maxh\Nominatim\Nominatim;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;

class CachedLocationCoordLookup extends LocationCoordLookup
{
    const CACHE_TTL = 3600;

    protected FilesystemAdapter $cache;

    public function __construct()
    {
        $this->cache = new FilesystemAdapter('kidicalmass-nominatim', self::CACHE_TTL);

        parent::__construct();
    }

    public function lookupCoordsForRideLocation(Ride $ride): Ride
    {
        if (!$ride->getCity() || !$ride->getLocation()) {
            return $ride;
        }

        $key = md5(sprintf('%s-%s', $ride->getCity()->getName(), $ride->getLocation()));

        $result = $this->cache->get($key, function (ItemInterface $item) use ($ride): array {
            $item->expiresAfter(self::CACHE_TTL);

            $search = $this->buildSearch($ride);

            $resultList = $this->lookup($search);

            if (1 === count($resultList)) {
                $result = array_pop($resultList);
            } else {
                $result = [];
            }

            return $result;
        });

        $ride = $this->assignPropertyValues($ride, $result);

        return $ride;
    }
}
