<?php declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\Ride;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RideTest extends TestCase
{
    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    #[Test]
    public function freshRideHasDateTimeAndCreatedAtInitialisedToNow(): void
    {
        $ride = new Ride();

        self::assertTrue($ride->hasDateTime());
        self::assertSame('2026-04-01 12:00:00', $ride->getDateTime()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-01 12:00:00', $ride->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertNull($ride->getUpdatedAt());
    }

    #[Test]
    public function freshRideHasNoIdentityOrPayloadFields(): void
    {
        $ride = new Ride();

        self::assertNull($ride->getId());
        self::assertNull($ride->getCityName());
        self::assertNull($ride->getCity());
        self::assertNull($ride->getSlug());
        self::assertFalse($ride->hasSlug());
        self::assertNull($ride->getTitle());
        self::assertNull($ride->getDescription());
        self::assertNull($ride->getLocation());
        self::assertNull($ride->getLatitude());
        self::assertNull($ride->getLongitude());
        self::assertNull($ride->getRideType());
    }

    #[Test]
    public function dateTimeCanBeCleared(): void
    {
        $ride = (new Ride())->setDateTime(null);

        self::assertFalse($ride->hasDateTime());
        self::assertNull($ride->getDateTime());
    }

    #[Test]
    public function slugCanBeCleared(): void
    {
        $ride = (new Ride())->setSlug('x')->setSlug(null);

        self::assertFalse($ride->hasSlug());
    }

    #[Test]
    public function settersAreFluent(): void
    {
        $ride = new Ride();

        self::assertSame($ride, $ride->setCityName('Berlin')->setTitle('t')->setDescription('d')->setLocation('l')->setLatitude(1.0)->setLongitude(2.0)->setSlug('s')->setRideType('KIDICAL_MASS')->setCity(Fixtures::city('Berlin')));
    }

    #[Test]
    public function descriptionAndRideTypeCanBeCleared(): void
    {
        $ride = (new Ride())->setDescription('d')->setRideType('KIDICAL_MASS');

        $ride->setDescription(null)->setRideType(null);

        self::assertNull($ride->getDescription());
        self::assertNull($ride->getRideType());
    }

    #[Test]
    public function createdAtAcceptsCarbon(): void
    {
        $ride = (new Ride())->setCreatedAt(new Carbon('2026-01-01 00:00:00', 'UTC'));

        self::assertSame(1767225600, $ride->getCreatedAt()->getTimestamp());
    }

    #[Test]
    public function createdAtAndUpdatedAtAcceptAnyDateTimeAndStoreCarbon(): void
    {
        $ride = (new Ride())
            ->setCreatedAt(new \DateTime('2026-01-01 00:00:00', new \DateTimeZone('UTC')))
            ->setUpdatedAt(new \DateTimeImmutable('2026-01-02 00:00:00', new \DateTimeZone('UTC')));

        self::assertInstanceOf(Carbon::class, $ride->getCreatedAt());
        self::assertInstanceOf(Carbon::class, $ride->getUpdatedAt());
        self::assertSame(1767225600, $ride->getCreatedAt()->getTimestamp());
        self::assertSame(1767312000, $ride->getUpdatedAt()->getTimestamp());
    }

    #[Test]
    public function createdAtAndUpdatedAtCanBeCleared(): void
    {
        $ride = (new Ride())->setCreatedAt(null)->setUpdatedAt(null);

        self::assertNull($ride->getCreatedAt());
        self::assertNull($ride->getUpdatedAt());
    }
}
