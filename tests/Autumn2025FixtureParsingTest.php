<?php declare(strict_types=1);

namespace App\Tests;

use App\CityFetcher\CityFetcherInterface;
use App\Model\City;
use App\Model\CitySlug;
use App\RideBuilder\RideBuilder;
use App\RideBuilder\SlugGenerator;
use PHPUnit\Framework\TestCase;

class Autumn2025FixtureParsingTest extends TestCase
{
    public function testFixtureCanBeParsedAndProducesValidRidesForTypicalEntries(): void
    {
        $fixturePath = __DIR__ . '/autumn2025.json';
        $raw = file_get_contents($fixturePath);

        $this->assertNotFalse($raw, 'Fixture tests/autumn2025.json konnte nicht gelesen werden.');

        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);

        $this->assertIsObject($decoded);
        $this->assertSame('FeatureCollection', $decoded->type);
        $this->assertIsArray($decoded->features);
        $this->assertGreaterThan(10, count($decoded->features));

        // CityFetcher wird hier bewusst gemockt, damit der Test nicht von Netzwerk/Geo-Services abhängt.
        // Für mindestens ein Feature liefern wir aber eine City zurück, um die Slug-Generierung mitzutesten.
        $berlinCity = (new City())
            ->setId(1)
            ->setName('Berlin')
            ->setTimezone('Europe/Berlin')
            ->setLatitude(52.52)
            ->setLongitude(13.405)
            ->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));

        $cityFetcher = $this->createMock(CityFetcherInterface::class);
        $cityFetcher->method('getCityForCoord')->willReturnCallback(
            function(float $latitude, float $longitude) use ($berlinCity): ?City {
                // Berlin-Feature(s) aus der Fixture haben ungefähr diese Koordinaten.
                if (abs($latitude - 52.52284) < 0.05 && abs($longitude - 13.363477) < 0.05) {
                    return $berlinCity;
                }

                return null;
            }
        );

        $builder = new RideBuilder($cityFetcher, new SlugGenerator());

        // (1) Eine „normale“ Zeitspezifikation muss funktionieren.
        $aschaffenburgFeature = $this->findFeatureByCityName($decoded->features, 'Aschaffenburg');
        $this->assertNotNull($aschaffenburgFeature, 'Erwartetes Feature "Aschaffenburg" nicht in Fixture gefunden.');

        $aschaffenburgRide = $builder->buildFromFeature($aschaffenburgFeature);

        $this->assertNotNull($aschaffenburgRide);
        $this->assertSame('Aschaffenburg', $aschaffenburgRide->getCityName());
        $this->assertSame('KIDICAL_MASS', $aschaffenburgRide->getRideType());
        $this->assertNotNull($aschaffenburgRide->getDateTime());
        $this->assertSame('2025-09-28 15:00', $aschaffenburgRide->getDateTime()->copy()->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));

        $this->assertNotNull($aschaffenburgRide->getLatitude());
        $this->assertNotNull($aschaffenburgRide->getLongitude());
        $this->assertEqualsWithDelta(49.975761, (float) $aschaffenburgRide->getLatitude(), 0.000001);
        $this->assertEqualsWithDelta(9.143982, (float) $aschaffenburgRide->getLongitude(), 0.000001);

        $this->assertNotEmpty((string) $aschaffenburgRide->getTitle());
        $this->assertStringContainsString('Kidical Mass Aschaffenburg', (string) $aschaffenburgRide->getTitle());

        // (2) Feature mit Sonderzeichen im Stadtnamen (Slugify/UTF-8) muss zumindest verarbeitbar sein.
        $banskaFeature = $this->findFeatureByCityName($decoded->features, 'Banská Bystrica');
        $this->assertNotNull($banskaFeature, 'Erwartetes Feature "Banská Bystrica" nicht in Fixture gefunden.');

        $banskaRide = $builder->buildFromFeature($banskaFeature);
        $this->assertNotNull($banskaRide);
        $this->assertSame('Banská Bystrica', $banskaRide->getCityName());
        $this->assertNotNull($banskaRide->getDateTime());
        $this->assertSame('2025-09-15 15:30', $banskaRide->getDateTime()->copy()->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));

        // (3) Zeitstrings mit Klammern/Zeitrange sind in der Realität vorhanden – aktuell erwarten wir: wird verworfen (null).
        $rangeFeature = $this->findFeatureById($decoded->features, 'rkyRQ');
        $this->assertNotNull($rangeFeature, 'Erwartetes Feature id=rkyRQ nicht in Fixture gefunden.');
        $this->assertSame('09:00 (09:00 - 12:00)', $rangeFeature->properties->Zeit);

        $rangeRide = $builder->buildFromFeature($rangeFeature);
        $this->assertNull(
            $rangeRide,
            'Zeitfenster-Strings sollten aktuell nicht parsbar sein und daher zu null führen (bewusstes Verhalten zum Schutz vor falschen Daten).'
        );

        // (4) Leere Zeit ist in der Realität vorhanden. Aktuelles Verhalten: Carbon parst das als Tagesbeginn (00:00).
        $emptyTimeFeature = $this->findFeatureById($decoded->features, '4qpAr');
        $this->assertNotNull($emptyTimeFeature, 'Erwartetes Feature id=4qpAr (Zeit = "") nicht in Fixture gefunden.');
        $this->assertSame('', $emptyTimeFeature->properties->Zeit);

        $emptyTimeRide = $builder->buildFromFeature($emptyTimeFeature);
        $this->assertNotNull($emptyTimeRide);
        $this->assertSame('Colares - Sintra', $emptyTimeRide->getCityName());
        $this->assertNotNull($emptyTimeRide->getDateTime());
        $this->assertSame('2025-10-25 00:00', $emptyTimeRide->getDateTime()->copy()->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));

        // (5) Berlin-Feature: wenn CityFetcher eine City liefert, muss ein Slug generiert werden.
        $berlinFeature = $this->findFeatureById($decoded->features, 'DXJaL');
        $this->assertNotNull($berlinFeature, 'Erwartetes Berlin-Feature id=DXJaL nicht in Fixture gefunden.');

        $berlinRide = $builder->buildFromFeature($berlinFeature);
        $this->assertNotNull($berlinRide);
        $this->assertSame('Berlin', $berlinRide->getCityName());
        $this->assertNotNull($berlinRide->getCity());
        $this->assertNotNull($berlinRide->getSlug());
        $this->assertSame('kidical-mass-berlin-september-2025', $berlinRide->getSlug());
    }

    /**
     * @param array<int, \stdClass> $features
     */
    private function findFeatureByCityName(array $features, string $cityName): ?\stdClass
    {
        foreach ($features as $feature) {
            $name = $feature->properties->name ?? $feature->properties->Name ?? null;
            if ($name !== null && trim((string) $name) === $cityName) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * @param array<int, \stdClass> $features
     */
    private function findFeatureById(array $features, string $id): ?\stdClass
    {
        foreach ($features as $feature) {
            if (isset($feature->id) && (string) $feature->id === $id) {
                return $feature;
            }
        }

        return null;
    }
}

