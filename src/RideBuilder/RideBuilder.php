<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\CityFetcher\CityFetcherInterface;
use App\LocationCoordLookup\LocationCoordLookupInterface;
use App\Model\City;
use App\Model\Ride;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Symfony\Component\DomCrawler\Crawler;

class RideBuilder implements RideBuilderInterface
{
    protected LocationCoordLookupInterface $locationCoordLookup;
    protected CityFetcherInterface $cityFetcher;
    protected SlugGeneratorInterface $slugGenerator;

    public function __construct(LocationCoordLookupInterface $locationCoordLookup, CityFetcherInterface $cityFetcher, SlugGeneratorInterface $slugGenerator)
    {
        $this->locationCoordLookup = $locationCoordLookup;
        $this->cityFetcher = $cityFetcher;
        $this->slugGenerator = $slugGenerator;
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

        $dateTime = $this->generateDateTime($city, $feature->properties->Tag, $feature->properties->Uhrzeit);

        if (!$dateTime) {
            return null;
        }

        $ride->setDateTime($dateTime);

        $location = $feature->properties->Startort;
        $ride->setLocation($location);

        $ride = $this->lookupLocation($ride);

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

    protected function generateDateTime(City $city = null, string $dayString, string $timeString): ?Carbon
    {
        $timezoneString = $city ? $city->getTimezone() : 'Europe/Berlin';

        try {
            $day = $dayString;
            $time = str_replace(['Uhr', 'folgt'], ['', ''], $timeString);

            $dateTimeString = sprintf('%s %s', $day, $time);
            $dateTime = new Carbon($dateTimeString, $timezoneString);

            return $dateTime;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Our geojson already provides latitude and longitude, but those values are not placed at the location, but in the
     * city center to be displayed in the large map. And that’s why we do another lookup here.
     */
    protected function lookupLocation(Ride $ride): Ride
    {
        return $this->locationCoordLookup->lookupCoordsForRideLocation($ride);
    }

    protected function extractCityName(\stdClass $feature): string
    {
        $cityName = $feature->properties->name ?? $feature->properties->Name;

        $cityName = trim($cityName);

        return $cityName;
    }
}
