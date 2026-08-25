<?php declare(strict_types=1);

namespace App\Tests\Double;

use App\CityFetcher\CityFetcherInterface;
use App\Model\City;
use App\Model\Ride;

/**
 * Deterministic replacement for the HTTP-backed CityFetcher.
 *
 * Note: RideBuilder relies on getCityListForCoord(), which is NOT part of
 * CityFetcherInterface; this double provides it explicitly.
 */
final class InMemoryCityFetcher implements CityFetcherInterface
{
    /** @var list<City> */
    private array $citiesForCoord = [];

    /** @var array<string, City> */
    private array $citiesByName = [];

    /** @var list<array{0: float, 1: float}> */
    private array $coordLookups = [];

    /** @param list<City> $cities */
    public function returnForCoord(array $cities): self
    {
        $this->citiesForCoord = $cities;

        return $this;
    }

    public function returnForName(string $name, City $city): self
    {
        $this->citiesByName[$name] = $city;

        return $this;
    }

    public function getCityForRide(Ride $ride): ?City
    {
        return $this->getCityForName((string) $ride->getCityName());
    }

    public function getCityForName(string $name): ?City
    {
        return $this->citiesByName[$name] ?? null;
    }

    public function getCityForCoord(float $latitude, float $longitude): ?City
    {
        $list = $this->getCityListForCoord($latitude, $longitude);

        return array_pop($list);
    }

    /** @return list<City> */
    public function getCityListForCoord(float $latitude, float $longitude): array
    {
        $this->coordLookups[] = [$latitude, $longitude];

        return $this->citiesForCoord;
    }

    /** @return list<array{0: float, 1: float}> */
    public function coordLookups(): array
    {
        return $this->coordLookups;
    }
}
