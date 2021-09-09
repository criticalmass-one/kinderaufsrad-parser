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

    public function buildFromFeature(\stdClass $feature): Ride
    {
        $ride = new Ride();

        $city = $this->cityFetcher->getCityForName($feature->properties->name ?? $feature->properties->Name);

        if (!$city) {
            return $ride;
        }

        $ride->setCity($city);

        $day = $feature->properties->Tag;
        $time = $feature->properties->Uhrzeit;
        $location = $feature->properties->Startort;

        $latitude = $feature->geometry->coordinates[1];
        $longitude = $feature->geometry->coordinates[0];

        $title = $this->generateTitle($ride);

        $ride
            ->setTitle($title)
            //->setDateTime($this->findDateTime($crawler, $city))
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
}
