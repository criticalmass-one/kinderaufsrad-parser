<?php declare(strict_types=1);

namespace App\Tests;

use App\CityFetcher\CityFetcherInterface;
use App\Model\City;
use App\Model\CitySlug;
use App\RideBuilder\RideBuilder;
use App\RideBuilder\SlugGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RideBuilderTest extends TestCase
{
    public function testBuildFromFeatureReturnsNullWhenDateOrTimeMissing(): void
    {
        $cityFetcher = $this->createMock(CityFetcherInterface::class);
        $cityFetcher->method('getCityForCoord')->willReturn(null);

        $builder = new RideBuilder($cityFetcher, new SlugGenerator());

        $feature = (object) [
            'geometry' => (object) ['coordinates' => [13.405, 52.52]],
            'properties' => (object) [
                'name' => 'Berlin',
                // Datum/Zeit fehlen absichtlich
            ],
        ];

        $ride = $builder->buildFromFeature($feature);

        $this->assertNull($ride);
    }

    public function testBuildFromFeatureBuildsRideAndGeneratesSlug(): void
    {
        $city = (new City())
            ->setId(1)
            ->setName('Berlin')
            ->setTimezone('Europe/Berlin')
            ->setLatitude(52.52)
            ->setLongitude(13.405)
            ->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));

        $cityFetcher = $this->createMock(CityFetcherInterface::class);
        $cityFetcher->method('getCityForCoord')->willReturn($city);

        $builder = new RideBuilder($cityFetcher, new SlugGenerator());

        $feature = (object) [
            'geometry' => (object) ['coordinates' => [13.405, 52.52]],
            'properties' => (object) [
                'name' => 'Berlin',
                'Datum' => '2023-04-12',
                'Zeit' => '15:00 Uhr',
                'Start' => 'Brandenburger Tor',
                'Besonderheit' => 'Helme empfohlen',
            ],
        ];

        $ride = $builder->buildFromFeature($feature);

        $this->assertNotNull($ride);
        $this->assertSame(52.52, $ride->getLatitude());
        $this->assertSame(13.405, $ride->getLongitude());
        $this->assertSame('Berlin', $ride->getCityName());
        $this->assertSame('Brandenburger Tor', $ride->getLocation());
        $this->assertSame('Helme empfohlen', $ride->getDescription());
        $this->assertSame('KIDICAL_MASS', $ride->getRideType());

        $this->assertNotNull($ride->getCity());
        $this->assertSame('Berlin', $ride->getCity()->getName());

        $this->assertNotNull($ride->getDateTime());
        $this->assertSame('2023-04-12 15:00', $ride->getDateTime()->copy()->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));

        $this->assertNotNull($ride->getSlug());
        $this->assertSame('kidical-mass-berlin-april-2023', $ride->getSlug());
        $this->assertStringContainsString('Kidical Mass Berlin', (string) $ride->getTitle());
    }

    public function testBuildFromFeatureSetsCityWhenFetcherReturnsOne(): void
    {
        $city = (new City())
            ->setId(1)
            ->setName('Berlin')
            ->setTimezone('Europe/Berlin')
            ->setLatitude(52.52)
            ->setLongitude(13.405)
            ->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));

        $cityFetcher = $this->createMock(CityFetcherInterface::class);
        $cityFetcher->method('getCityForCoord')->willReturn($city);

        $builder = new RideBuilder($cityFetcher, new SlugGenerator());

        $feature = (object) [
            'geometry' => (object) ['coordinates' => [13.405, 52.52]],
            'properties' => (object) [
                'name' => 'Berlin Pankow',
                'Datum' => Carbon::parse('2023-04-12')->format('Y-m-d'),
                'Zeit' => '15:00',
            ],
        ];

        $ride = $builder->buildFromFeature($feature);

        $this->assertNotNull($ride);
        $this->assertNotNull($ride->getCity());
        $this->assertSame('Berlin', $ride->getCity()->getName());
    }
}

