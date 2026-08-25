<?php declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\City;
use App\Model\CitySlug;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CityTest extends TestCase
{
    #[Test]
    public function defaultsToBerlinTimezoneWithoutNameOrSlug(): void
    {
        $city = new City();

        self::assertSame('Europe/Berlin', $city->getTimezone());
        self::assertNull($city->getName());
        self::assertNull($city->getMainSlug());
    }

    #[Test]
    public function freshCityHasNoId(): void
    {
        self::assertNull((new City())->getId());
    }

    #[Test]
    public function coordinatesAreUninitialisedUntilSet(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/must not be accessed before initialization/');

        (new City())->getLatitude();
    }

    #[Test]
    public function settersAreFluentAndValuesRoundTrip(): void
    {
        $slug = (new CitySlug())->setId(3)->setSlug('wien');
        $city = new City();

        self::assertSame($city, $city->setId(1)->setName('Wien')->setTimezone('Europe/Vienna')->setLatitude(48.2)->setLongitude(16.37)->setMainSlug($slug));
        self::assertSame(1, $city->getId());
        self::assertSame('Wien', $city->getName());
        self::assertSame('Europe/Vienna', $city->getTimezone());
        self::assertSame(48.2, $city->getLatitude());
        self::assertSame(16.37, $city->getLongitude());
        self::assertSame($slug, $city->getMainSlug());
    }

    #[Test]
    public function citySlugRoundTrips(): void
    {
        $slug = (new CitySlug())->setId(3)->setSlug('wien');

        self::assertSame(3, $slug->getId());
        self::assertSame('wien', $slug->getSlug());
    }

    #[Test]
    public function citySlugIsUninitialisedUntilSet(): void
    {
        $this->expectException(\Error::class);

        (new CitySlug())->getSlug();
    }
}
