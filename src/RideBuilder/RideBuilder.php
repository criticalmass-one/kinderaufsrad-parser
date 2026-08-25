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

        $latitude = $feature->geometry->coordinates[1];
        $longitude = $feature->geometry->coordinates[0];

        $ride
            ->setLatitude($latitude)
            ->setLongitude($longitude)
        ;

        $cityName = $this->extractCityName($feature);
        $ride->setCityName($cityName);

        $city = $this->matchCity($cityName, $this->cityFetcher->getCityListForCoord($latitude, $longitude));

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

        if (isset($feature->properties->Besonderheit)) {
            $description = $feature->properties->Besonderheit;
            $ride->setDescription($description);
        }

        $title = $this->generateTitle($ride);

        $ride
            ->setTitle($title)
            ->setRideType('KIDICAL_MASS');

        $ride = $this->slugGenerator->generateForRide($ride);

        return $ride;
    }

    /**
     * Picks the CM city whose name occurs in the OSM name as a whole word, longest name first,
     * so that "Wiener Neustadt" is not attributed to "Wien" and "Ulm & Neu-Ulm" prefers
     * "Neu-Ulm" over "Ulm".
     *
     * @param list<City> $cityList
     */
    protected function matchCity(string $cityName, array $cityList): ?City
    {
        usort($cityList, static fn(City $a, City $b): int => mb_strlen((string) $b->getName()) <=> mb_strlen((string) $a->getName()));

        foreach ($cityList as $city) {
            $name = (string) $city->getName();

            if ($name === '') {
                continue;
            }

            // Plain \b fails for names like "Frankfurt (Oder)" that end in a non-word character,
            // so a boundary is only required on the sides where the name itself has a word character.
            $leading = preg_match('/^[\p{L}\p{N}]/u', $name) ? '(?<![\p{L}\p{N}])' : '';
            $trailing = preg_match('/[\p{L}\p{N}]$/u', $name) ? '(?![\p{L}\p{N}])' : '';

            if (preg_match(sprintf('/%s%s%s/u', $leading, preg_quote($name, '/'), $trailing), $cityName)) {
                return $city;
            }
        }

        return null;
    }

    protected function generateTitle(Ride $ride): string
    {
        return sprintf('Kidical Mass %s %s', $ride->getCityName(), $ride->getDateTime()->format('d.m.Y'));
    }

    protected function generateDateTime(string $dayString, string $timeString, ?City $city = null): ?Carbon
    {
        $timezoneString = $city ? $city->getTimezone() : 'Europe/Berlin';

        $time = $this->normalizeTime($timeString);

        if ($time === null) {
            return null;
        }

        try {
            return new Carbon(sprintf('%s %s', $dayString, $time), $timezoneString);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Reduces the heterogeneous UMap "Zeit" value to "HH:MM".
     *
     * Real values look like "15:00 Uhr", "15 Uhr", "15:00 ()", "11:00 (11:00 - 12:30)",
     * "08:00 (00:30 - )", " (11:00 - 12:30)", "15:00 - 17:00" or a placeholder such as
     * "folgt" / "Uhrzeit folgt". Everything from the first "(" onwards is ignored unless
     * nothing precedes it, in which case the first time inside the parentheses is used.
     * Values without any digit are placeholders and fall back to midnight; values with
     * digits that do not contain a recognisable time are rejected.
     */
    protected function normalizeTime(string $timeString): ?string
    {
        $timeString = trim($timeString);

        $parenthesisPosition = strpos($timeString, '(');

        if ($parenthesisPosition !== false) {
            $head = trim(substr($timeString, 0, $parenthesisPosition));
            $timeString = $head !== '' ? $head : substr($timeString, $parenthesisPosition + 1);
        }

        if (preg_match('/(?<!\d)(\d{1,2})[:.](\d{2})(?!\d)/', $timeString, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/(?<!\d)(\d{1,2})(?!\d)\s*(?:Uhr|h)?/i', $timeString, $matches)) {
            return sprintf('%02d:00', (int) $matches[1]);
        }

        if (!preg_match('/\d/', $timeString)) {
            return '00:00';
        }

        return null;
    }

    protected function extractCityName(\stdClass $feature): string
    {
        $cityName = $feature->properties->name ?? $feature->properties->Name;

        $cityName = trim($cityName);

        return $cityName;
    }
}
