<?php declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Model\City;
use App\Model\Ride;
use App\Serializer\Normalizer\RideNormalizer;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

final class RideNormalizerTest extends TestCase
{
    private RideNormalizer $normalizer;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'UTC'));
        $this->normalizer = new RideNormalizer();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    #[Test]
    public function normalizesAllApiFields(): void
    {
        $ride = Fixtures::ride()
            ->setCreatedAt(new Carbon('2026-03-01 08:00:00', 'UTC'))
            ->setUpdatedAt(new Carbon('2026-03-02 09:00:00', 'UTC'));

        self::assertSame([
            'slug' => 'kidical-mass-berlin-mai-2026',
            'title' => 'Kidical Mass Berlin 10.05.2026',
            'description' => 'Familienfreundliche Route',
            'date_time' => 1778418000,
            'location' => 'Rathausplatz',
            'latitude' => 52.52,
            'longitude' => 13.405,
            'created_at' => 1772352000,
            'updated_at' => 1772442000,
            'ride_type' => 'KIDICAL_MASS',
        ], $this->normalizer->normalize($ride));
    }

    #[Test]
    public function dateTimeIsSerializedAsUnixTimestampIndependentOfTimezone(): void
    {
        $berlin = Fixtures::ride(dateTime: new Carbon('2026-05-10 15:00', 'Europe/Berlin'));
        $utc = Fixtures::ride(dateTime: new Carbon('2026-05-10 13:00', 'UTC'));

        self::assertSame(
            $this->normalizer->normalize($berlin)['date_time'],
            $this->normalizer->normalize($utc)['date_time'],
        );
    }

    #[Test]
    public function freshRideNormalizesToNullsAndCurrentTimestamps(): void
    {
        $data = $this->normalizer->normalize(new Ride());

        self::assertNull($data['slug']);
        self::assertNull($data['title']);
        self::assertNull($data['description']);
        self::assertNull($data['location']);
        self::assertNull($data['latitude']);
        self::assertNull($data['longitude']);
        self::assertNull($data['updated_at']);
        self::assertNull($data['ride_type']);
        self::assertSame(1775044800, $data['date_time'], 'constructor initialises date_time with "now"');
        self::assertSame(1775044800, $data['created_at'], 'constructor initialises created_at with "now"');
    }

    #[Test]
    public function nullDateTimeIsNormalizedToNull(): void
    {
        $data = $this->normalizer->normalize((new Ride())->setDateTime(null));

        self::assertNull($data['date_time']);
    }

    #[Test]
    public function cityIsNotPartOfThePayload(): void
    {
        $data = $this->normalizer->normalize(Fixtures::ride());

        self::assertArrayNotHasKey('city', $data);
        self::assertArrayNotHasKey('city_name', $data);
        self::assertArrayNotHasKey('id', $data);
    }

    /**
     * criticalmass.in deserializes ride_type through BackedEnumNormalizer into RideTypeEnum
     * (since 2026-05-11), so the value must be the enum's backing string "KIDICAL_MASS".
     */
    #[Test]
    public function rideTypeIsSentAsEnumValue(): void
    {
        self::assertSame('KIDICAL_MASS', $this->normalizer->normalize(Fixtures::ride())['ride_type']);
    }

    #[Test]
    public function supportsOnlyRideInstances(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new Ride()));
        self::assertFalse($this->normalizer->supportsNormalization(new City()));
        self::assertFalse($this->normalizer->supportsNormalization(['slug' => 'x']));
        self::assertSame([Ride::class => true], $this->normalizer->getSupportedTypes('json'));
    }

    #[Test]
    public function producesExpectedJsonThroughSymfonySerializer(): void
    {
        $serializer = new Serializer([$this->normalizer], [new JsonEncoder()]);
        $ride = Fixtures::ride()->setCreatedAt(new Carbon('2026-03-01 08:00:00', 'UTC'));

        $json = $serializer->serialize($ride, 'json');

        self::assertJson($json);
        self::assertSame(
            '{"slug":"kidical-mass-berlin-mai-2026","title":"Kidical Mass Berlin 10.05.2026","description":"Familienfreundliche Route","date_time":1778418000,"location":"Rathausplatz","latitude":52.52,"longitude":13.405,"created_at":1772352000,"updated_at":null,"ride_type":"KIDICAL_MASS"}',
            $json,
        );
    }
}
