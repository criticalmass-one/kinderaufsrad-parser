<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\LocationCoordLookup\LocationCoordLookupInterface;
use App\Model\Ride;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class RideBuilder implements RideBuilderInterface
{
    protected LocationCoordLookupInterface $locationCoordLookup;

    public function __construct(LocationCoordLookupInterface $locationCoordLookup)
    {
        $this->locationCoordLookup = $locationCoordLookup;
    }

    public function buildWithCrawler(Crawler $crawler): Ride
    {
        $ride = new Ride();

        $title = $this->findTitle($crawler);

        if (!$title) {
            return $ride;
        }

        $cityName = $this->findCityName($title);

        if (!$cityName) {
            return $ride;
        }

        $ride
            ->setTitle($title)
            ->setCityName($cityName)
            ->setDateTime($this->findDateTime($crawler))
            ->setLocation($this->findLocation($crawler));

        $ride = $this->locationCoordLookup->lookupCoordsForRideLocation($ride);

        return $ride;
    }

    protected function findTitle(Crawler $crawler): ?string
    {
        $h2List = $crawler->filter('h2');

        foreach ($h2List as $h2Element) {
            if ($h2Element->textContent) {
                return $h2Element->textContent;
            }
        }

        return null;
    }

    protected function findCityName(string $title): string
    {
        return str_replace('Kidical Mass ', '', $title);
    }

    protected function findDateTime(Crawler $crawler): ?\DateTime
    {
        $dateTimeList = $crawler->filter('p > b > span');

        foreach ($dateTimeList as $dateTimeElement) {
            $germanDateTimeSpec = $dateTimeElement->textContent;
            $germanDateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber'], ['', '', '03.', '09.'], $germanDateTimeSpec);

            try {
                return Carbon::parseFromLocale($germanDateTimeSpec);

            } catch (\Exception $exception) {
                try {
                    $germanDateTimeSpec = str_replace(['x', 'X'], '', $germanDateTimeSpec);
                    return Carbon::parseFromLocale($germanDateTimeSpec);
                } catch (\Exception $exception) {

                }
            }
        }

        return null;
    }

    protected function findLocation(Crawler $crawler): ?string
    {

        $locationList = $crawler->filter('div span');

        foreach ($locationList as $locationElement) {
            $locationString = $locationElement->textContent;
            if (strpos($locationString, 'Start: ') === 0 && strpos($locationString, 'folgt') === false) {
                return str_replace('Start: ', '', $locationString);
            }
        }

        return null;
    }
}
