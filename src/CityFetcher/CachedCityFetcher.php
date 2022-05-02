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

        $cityJson = $this->cache->get($key, function () use ($name): string {
            $name = $this->fixCityName($name);

            $query = [
                'name' => $name,
            ];

            $response = $this->client->get(sprintf('/api/city?%s', http_build_query($query)));

            return $response->getBody()->getContents();
        });

        $cityList = $this->serializer->deserialize($cityJson, 'array<App\Model\City>', 'json');

        return array_pop($cityList);
    }
}
