<?php declare(strict_types=1);

namespace App\Tests;

use App\Model\City;
use App\Model\Ride;
use App\Serializer\Denormalizer\CityDenormalizer;
use App\Serializer\Denormalizer\RideDenormalizer;
use App\Serializer\Normalizer\RideNormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class SerializerTest extends TestCase
{
    public function testCityDenormalizerUsesDefaultsAndMainSlugIsOptional(): void
    {
        $denormalizer = new CityDenormalizer();

        $city = $denormalizer->denormalize([
            'id' => 1,
            'name' => 'Berlin',
            // timezone fehlt absichtlich
            'latitude' => 52.52,
            'longitude' => 13.405,
        ], City::class);

        $this->assertSame('Berlin', $city->getName());
        $this->assertSame('Europe/Berlin', $city->getTimezone());
        $this->assertNull($city->getMainSlug());
    }

    public function testCityDenormalizerRejectsNonArray(): void
    {
        $this->expectException(NotNormalizableValueException::class);

        (new CityDenormalizer())->denormalize('nope', City::class);
    }

    public function testRideDenormalizerDenormalizesCityAndTimestamps(): void
    {
        $rideDenormalizer = new RideDenormalizer(new CityDenormalizer());

        $ride = $rideDenormalizer->denormalize([
            'slug' => 'kidical-mass-berlin-april-2023',
            'title' => 'Kidical Mass Berlin',
            'ride_type' => 'KIDICAL_MASS',
            'date_time' => 1681257600,
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => null,
            'city' => [
                'id' => 1,
                'name' => 'Berlin',
                'timezone' => 'Europe/Berlin',
                'latitude' => 52.52,
                'longitude' => 13.405,
            ],
        ], Ride::class);

        $this->assertInstanceOf(Ride::class, $ride);
        $this->assertSame('Berlin', $ride->getCityName());
        $this->assertNotNull($ride->getCity());
        $this->assertInstanceOf(Carbon::class, $ride->getDateTime());
        $this->assertInstanceOf(Carbon::class, $ride->getCreatedAt());
        $this->assertNull($ride->getUpdatedAt());
    }

    public function testRideDenormalizerRejectsInvalidCarbonType(): void
    {
        $this->expectException(NotNormalizableValueException::class);

        $rideDenormalizer = new RideDenormalizer(new CityDenormalizer());
        $rideDenormalizer->denormalize([
            'date_time' => ['nope'],
        ], Ride::class);
    }

    public function testRideNormalizerExportsTimestampsAsIntegers(): void
    {
        $normalizer = new RideNormalizer();

        $ride = (new Ride())
            ->setSlug('s')
            ->setTitle('t')
            ->setDescription('d')
            ->setLocation('l')
            ->setLatitude(1.0)
            ->setLongitude(2.0)
            ->setRideType('KIDICAL_MASS');

        $ride->setDateTime(Carbon::parse('2023-01-02 03:04:05'));
        $ride->setCreatedAt(Carbon::parse('2023-01-01 00:00:00'));
        $ride->setUpdatedAt(Carbon::parse('2023-01-03 00:00:00'));

        $data = $normalizer->normalize($ride);

        $this->assertSame('s', $data['slug']);
        $this->assertSame('t', $data['title']);
        $this->assertSame('d', $data['description']);
        $this->assertSame('l', $data['location']);
        $this->assertSame(1.0, $data['latitude']);
        $this->assertSame(2.0, $data['longitude']);
        $this->assertIsInt($data['date_time']);
        $this->assertIsInt($data['created_at']);
        $this->assertIsInt($data['updated_at']);
        $this->assertSame('KIDICAL_MASS', $data['ride_type']);
    }
}

