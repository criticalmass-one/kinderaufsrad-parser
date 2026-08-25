<?php declare(strict_types=1);

namespace App\Tests\Fixture;

use App\Model\City;
use App\Model\CitySlug;
use App\Model\Ride;
use Carbon\Carbon;

final class Fixtures
{
    /**
     * Builds a UMap GeoJSON feature the way json_decode($json, null) returns it (stdClass tree).
     *
     * @param array<string, mixed> $properties
     */
    public static function feature(array $properties, float $latitude = 52.52, float $longitude = 13.405): \stdClass
    {
        $feature = new \stdClass();
        $feature->type = 'Feature';
        $feature->geometry = new \stdClass();
        $feature->geometry->type = 'Point';
        $feature->geometry->coordinates = [$longitude, $latitude];
        $feature->properties = (object) $properties;

        return $feature;
    }

    /** @param list<\stdClass> $features */
    public static function featureCollectionJson(array $features): string
    {
        return json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR);
    }

    public static function city(string $name, ?string $slug = null, int $id = 1, string $timezone = 'Europe/Berlin'): City
    {
        $city = (new City())
            ->setId($id)
            ->setName($name)
            ->setTimezone($timezone)
            ->setLatitude(52.52)
            ->setLongitude(13.405);

        if ($slug !== null) {
            $city->setMainSlug((new CitySlug())->setId($id)->setSlug($slug));
        }

        return $city;
    }

    public static function ride(string $cityName = 'Berlin', ?City $city = null, ?Carbon $dateTime = null): Ride
    {
        return (new Ride())
            ->setCityName($cityName)
            ->setCity($city ?? self::city($cityName, strtolower($cityName)))
            ->setDateTime($dateTime ?? new Carbon('2026-05-10 15:00:00', 'Europe/Berlin'))
            ->setTitle(sprintf('Kidical Mass %s 10.05.2026', $cityName))
            ->setDescription('Familienfreundliche Route')
            ->setLocation('Rathausplatz')
            ->setLatitude(52.52)
            ->setLongitude(13.405)
            ->setSlug(sprintf('kidical-mass-%s-mai-2026', strtolower($cityName)))
            ->setRideType('KIDICAL_MASS');
    }

    /** @param array<string, mixed> $overrides */
    public static function rideApiJson(array $overrides = []): string
    {
        $data = array_merge([
            'id' => 4711,
            'slug' => 'kidical-mass-berlin-mai-2026',
            'title' => 'Kidical Mass Berlin 10.05.2026',
            'description' => 'Familienfreundliche Route',
            'date_time' => 1778418000,
            'location' => 'Rathausplatz',
            'latitude' => 52.52,
            'longitude' => 13.405,
            'ride_type' => 'KIDICAL_MASS',
            'created_at' => 1770000000,
            'updated_at' => 1770000100,
            'city' => [
                'id' => 1,
                'name' => 'Berlin',
                'timezone' => 'Europe/Berlin',
                'latitude' => 52.52,
                'longitude' => 13.405,
                'main_slug' => ['id' => 9, 'slug' => 'berlin'],
            ],
        ], $overrides);

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
