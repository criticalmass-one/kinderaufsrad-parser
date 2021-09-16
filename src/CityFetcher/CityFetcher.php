<?php declare(strict_types=1);

namespace App\CityFetcher;

use App\Model\City;
use App\Model\Ride;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class CityFetcher implements CityFetcherInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
        ]);
    }

    public function getCityForRide(Ride $ride): ?City
    {
        return $this->getCityForName($ride->getCityName());
    }

    public function getCityForName(string $name): ?City
    {
        $name = $this->fixCityName($name);

        $query = [
            'name' => $name,
        ];

        $response = $this->client->get(sprintf('/api/city?%s', http_build_query($query)));

        $cityList = $this->serializer->deserialize($response->getBody()->getContents(), 'array<App\Model\City>', 'json');

        return array_pop($cityList);
    }

    protected function fixCityName(string $name): string
    {
        $name = str_replace(['(AU)', '(AU )', '(CH)', '(FR)'], '', $name);

        return trim($name);
    }
}
