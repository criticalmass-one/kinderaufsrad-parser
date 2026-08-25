<?php declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Model\City;
use App\Model\Ride;
use App\Serializer\Denormalizer\CityDenormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

final class CityDenormalizerTest extends TestCase
{
    private CityDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new CityDenormalizer();
    }

    #[Test]
    public function denormalizesCompleteCity(): void
    {
        $city = $this->denormalizer->denormalize([
            'id' => 42,
            'name' => 'Wien',
            'timezone' => 'Europe/Vienna',
            'latitude' => 48.2,
            'longitude' => 16.37,
            'main_slug' => ['id' => 7, 'slug' => 'wien'],
        ], City::class);

        self::assertSame(42, $city->getId());
        self::assertSame('Wien', $city->getName());
        self::assertSame('Europe/Vienna', $city->getTimezone());
        self::assertSame(48.2, $city->getLatitude());
        self::assertSame(16.37, $city->getLongitude());
        self::assertSame(7, $city->getMainSlug()?->getId());
        self::assertSame('wien', $city->getMainSlug()?->getSlug());
    }

    #[Test]
    public function appliesDefaultsForMissingFields(): void
    {
        $city = $this->denormalizer->denormalize([], City::class);

        self::assertSame(0, $city->getId());
        self::assertSame('', $city->getName());
        self::assertSame('Europe/Berlin', $city->getTimezone());
        self::assertSame(0.0, $city->getLatitude());
        self::assertSame(0.0, $city->getLongitude());
        self::assertNull($city->getMainSlug());
    }

    #[Test]
    public function integerCoordinatesAreWidenedToFloat(): void
    {
        $city = $this->denormalizer->denormalize(['latitude' => 52, 'longitude' => 13], City::class);

        self::assertSame(52.0, $city->getLatitude());
        self::assertSame(13.0, $city->getLongitude());
    }

    #[Test]
    public function mainSlugWithoutSlugKeyIsIgnored(): void
    {
        $city = $this->denormalizer->denormalize(['main_slug' => ['id' => 7]], City::class);

        self::assertNull($city->getMainSlug());
    }

    #[Test]
    public function nullMainSlugIsIgnored(): void
    {
        $city = $this->denormalizer->denormalize(['main_slug' => null], City::class);

        self::assertNull($city->getMainSlug());
    }

    #[Test]
    public function rejectsNonArrayData(): void
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->denormalizer->denormalize('Berlin', City::class);
    }

    #[Test]
    public function supportsOnlyCityType(): void
    {
        self::assertTrue($this->denormalizer->supportsDenormalization([], City::class));
        self::assertFalse($this->denormalizer->supportsDenormalization([], Ride::class));
        self::assertSame([City::class => true], $this->denormalizer->getSupportedTypes(null));
    }

    #[Test]
    public function deserializesCityListsThroughTheSerializer(): void
    {
        $serializer = new Serializer([$this->denormalizer, new ArrayDenormalizer()], [new JsonEncoder()]);

        $cities = $serializer->deserialize('[{"id":1,"name":"Berlin"},{"id":2,"name":"Potsdam"}]', City::class . '[]', 'json');

        self::assertCount(2, $cities);
        self::assertContainsOnlyInstancesOf(City::class, $cities);
        self::assertSame(['Berlin', 'Potsdam'], array_map(static fn(City $city): ?string => $city->getName(), $cities));
    }
}
