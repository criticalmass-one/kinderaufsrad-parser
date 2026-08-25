<?php declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Model\City;
use App\Model\Ride;
use App\Serializer\Denormalizer\CityDenormalizer;
use App\Serializer\Denormalizer\RideDenormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class RideDenormalizerTest extends TestCase
{
    private RideDenormalizer $denormalizer;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'UTC'));
        $this->denormalizer = new RideDenormalizer(new CityDenormalizer());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    /** @return array<string, mixed> */
    private static function apiData(): array
    {
        return json_decode(\App\Tests\Fixture\Fixtures::rideApiJson(), true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function denormalizesFullApiResponse(): void
    {
        $ride = $this->denormalizer->denormalize(self::apiData(), Ride::class);

        self::assertSame('kidical-mass-berlin-mai-2026', $ride->getSlug());
        self::assertSame('Kidical Mass Berlin 10.05.2026', $ride->getTitle());
        self::assertSame('Familienfreundliche Route', $ride->getDescription());
        self::assertSame('Rathausplatz', $ride->getLocation());
        self::assertSame(52.52, $ride->getLatitude());
        self::assertSame(13.405, $ride->getLongitude());
        self::assertSame('KIDICAL_MASS', $ride->getRideType());
        self::assertSame(1778418000, $ride->getDateTime()?->timestamp);
        self::assertSame(1770000000, $ride->getCreatedAt()->getTimestamp());
        self::assertSame(1770000100, $ride->getUpdatedAt()?->getTimestamp());
    }

    #[Test]
    public function nestedCityIsDenormalizedAndCityNameMirrorsIt(): void
    {
        $ride = $this->denormalizer->denormalize(self::apiData(), Ride::class);

        self::assertInstanceOf(City::class, $ride->getCity());
        self::assertSame('Berlin', $ride->getCity()->getName());
        self::assertSame('berlin', $ride->getCity()->getMainSlug()?->getSlug());
        self::assertSame('Berlin', $ride->getCityName());
    }

    #[Test]
    public function withoutCityBothCityAndCityNameStayNull(): void
    {
        $data = self::apiData();
        unset($data['city']);

        $ride = $this->denormalizer->denormalize($data, Ride::class);

        self::assertNull($ride->getCity());
        self::assertNull($ride->getCityName());
    }

    #[Test]
    public function idFromApiIsNotMapped(): void
    {
        self::assertNull($this->denormalizer->denormalize(self::apiData(), Ride::class)->getId());
    }

    #[Test]
    public function dateStringsAreAcceptedAsWell(): void
    {
        $data = self::apiData();
        $data['date_time'] = '2026-05-10T15:00:00+02:00';
        $data['updated_at'] = '2026-03-02 09:00:00';

        $ride = $this->denormalizer->denormalize($data, Ride::class);

        self::assertSame(1778418000, $ride->getDateTime()?->timestamp);
        self::assertSame('2026-03-02 09:00:00', $ride->getUpdatedAt()?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function missingDateTimeKeepsConstructorDefaultOfNow(): void
    {
        $data = self::apiData();
        unset($data['date_time'], $data['created_at'], $data['updated_at']);

        $ride = $this->denormalizer->denormalize($data, Ride::class);

        self::assertSame('2026-04-01 12:00:00', $ride->getDateTime()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-01 12:00:00', $ride->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertNull($ride->getUpdatedAt());
    }

    #[Test]
    public function nullDateTimeIsTreatedLikeMissing(): void
    {
        $data = self::apiData();
        $data['date_time'] = null;

        // isset() is false for null, so the constructor default ("now") survives.
        self::assertSame('2026-04-01 12:00:00', $this->denormalizer->denormalize($data, Ride::class)->getDateTime()?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function missingOptionalStringsBecomeNull(): void
    {
        $data = self::apiData();
        unset($data['title'], $data['slug'], $data['location'], $data['latitude'], $data['longitude']);

        $ride = $this->denormalizer->denormalize($data, Ride::class);

        self::assertNull($ride->getTitle());
        self::assertNull($ride->getSlug());
        self::assertNull($ride->getLocation());
        self::assertNull($ride->getLatitude());
        self::assertNull($ride->getLongitude());
    }

    /** @return iterable<string, array{0: string}> */
    public static function nonNullableFieldProvider(): iterable
    {
        yield 'description' => ['description'];
        yield 'ride_type' => ['ride_type'];
    }

    /**
     * Bug: Ride::setDescription()/setRideType() are declared with non-nullable string
     * parameters, but the denormalizer passes `$data[...] ?? null`. Any API response
     * without these fields (or with null — the API returns ride_type null for rides
     * whose type is unset) blows up with a TypeError instead of being denormalized.
     */
    #[Test]
    #[DataProvider('nonNullableFieldProvider')]
    public function missingNonNullableFieldCausesTypeError(string $field): void
    {
        $data = self::apiData();
        unset($data[$field]);

        $this->expectException(\TypeError::class);

        $this->denormalizer->denormalize($data, Ride::class);
    }

    #[Test]
    #[DataProvider('nonNullableFieldProvider')]
    public function nullNonNullableFieldCausesTypeError(string $field): void
    {
        $data = self::apiData();
        $data[$field] = null;

        $this->expectException(\TypeError::class);

        $this->denormalizer->denormalize($data, Ride::class);
    }

    #[Test]
    public function rejectsNonArrayData(): void
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->denormalizer->denormalize('{"slug": "x"}', Ride::class);
    }

    #[Test]
    public function supportsOnlyRideType(): void
    {
        self::assertTrue($this->denormalizer->supportsDenormalization([], Ride::class));
        self::assertFalse($this->denormalizer->supportsDenormalization([], City::class));
        self::assertSame([Ride::class => true], $this->denormalizer->getSupportedTypes('json'));
    }
}
