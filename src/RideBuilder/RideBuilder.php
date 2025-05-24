<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\CityFetcher\CityFetcherInterface;
use App\Model\City;
use App\Model\Ride;
use Carbon\Carbon;

class RideBuilder implements RideBuilderInterface
{
    public function __construct(protected CityFetcherInterface $cityFetcher, protected SlugGeneratorInterface $slugGenerator)
    {

    }

    public function buildFromFeature(\stdClass $feature): ?Ride
    {
        $ride = new Ride();

        $cityName = $this->extractCityName($feature);
        $ride->setCityName($cityName);
        $city = $this->cityFetcher->getCityForName($cityName);

        if ($city) {
            $ride->setCity($city);
        }

        if (!isset($feature->properties->Datum) || !isset($feature->properties->Zeit)) {
            return null;
        }

        $dateTime = $this->generateDateTime($feature->properties->Datum, $feature->properties->Zeit, $city);

        if (!$dateTime) {
            return null;
        }

        $ride->setDateTime($dateTime);

        if (isset($feature->properties->Start)) {
            $location = $feature->properties->Start;
            $ride->setLocation($location);
        }

        $latitude = $feature->geometry->coordinates[1];
        $longitude = $feature->geometry->coordinates[0];

        $ride
            ->setLatitude($latitude)
            ->setLongitude($longitude)
        ;

        $title = $this->generateTitle($ride);

        $ride
            ->setTitle($title)
            ->setRideType('KIDICAL_MASS');

        $ride = $this->slugGenerator->generateForRide($ride);

        return $ride;
    }

    protected function generateTitle(Ride $ride): string
    {
        return sprintf('Kidical Mass %s %s', $ride->getCityName(), $ride->getDateTime()->format('d.m.Y'));
    }

    protected function generateDateTime(string $dayString, string $timeString, City $city = null): ?Carbon
    {
        $timezoneString = $city ? $city->getTimezone() : 'Europe/Berlin';

        try {
            $day = $dayString;
            $time = str_replace(['Uhr', 'folgt'], ['', ''], $timeString);

            $dateTimeString = sprintf('%s %s', $day, $time);
            $dateTime = new Carbon($dateTimeString, $timezoneString);

            return $dateTime;
        } catch (\Exception) {
            return null;
        }
    }

    protected function extractCityName(\stdClass $feature): string
    {
        $cityName = $feature->properties->name ?? $feature->properties->Name;

        $cityName = trim($cityName);

        return $cityName;
    }
}
