<?php declare(strict_types=1);

namespace App\CityFetcher;

use App\Model\City;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CachedCityFetcher extends CityFetcher
{
    const CACHE_TTL = 3600;

    protected FilesystemAdapter $cache;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->cache = new FilesystemAdapter('kidicalmass-city', self::CACHE_TTL);

        parent::__construct($serializer, $criticalmassHostname);
    }

    public function getCityForName(string $name): ?City
    {
        $key = md5($name);

        return $this->cache->get($key, function () use ($name): City {
            $query = [
                'name' => $name,
            ];

            $response = $this->client->get(sprintf('/api/city?%s', http_build_query($query)));

            $city = $this->serializer->deserialize($response->getBody()->getContents(), City::class, 'json');

            return $city;
        });
    }
}
