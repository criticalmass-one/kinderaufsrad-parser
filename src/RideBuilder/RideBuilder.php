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

        $city = $this->cityFetcher->getCityForName($feature->properties->name ?? $feature->properties->Name);

        if (!$city) {
            return null;
        }

        $ride->setCity($city);

        $dateTime = $this->generateDateTime($city, $feature->properties->Tag, $feature->properties->Uhrzeit);

        if (!$dateTime) {
            return null;
        }

        $ride->setDateTime($dateTime);

        $location = $feature->properties->Startort;

        $latitude = $feature->geometry->coordinates[1];
        $longitude = $feature->geometry->coordinates[0];

        $title = $this->generateTitle($ride);

        $ride
            ->setTitle($title)
            ->setLocation($location)
            ->setRideType('KIDICAL_MASS')
            ->setLatitude($latitude)
            ->setLongitude($longitude)
        ;

        $ride = $this->slugGenerator->generateForRide($ride);

        return $ride;
    }

    protected function generateTitle(Ride $ride): string
    {
        return sprintf('Kidical Mass %s %s', $ride->getCity()->getName(), $ride->getDateTime()->format('d.m.Y'));
    }

    protected function generateDateTime(City $city, string $dayString, string $timeString): ?Carbon
    {
        try {
            $day = $dayString;
            $time = substr($timeString, 0, 0);

            $dateTimeString = sprintf('%s %s', $day, $time);
            $dateTime = new Carbon($dateTimeString, $city->getTimezone());

            return $dateTime;
        } catch (\Exception $exception) {
            return null;
        }
    }
}
