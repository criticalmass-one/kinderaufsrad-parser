<?php declare(strict_types=1);

namespace App\Tests\RideBuilder;

use App\Model\Ride;
use App\RideBuilder\SlugGenerator;
use App\Tests\Double\KnownBugTrait;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SlugGeneratorTest extends TestCase
{
    use KnownBugTrait;

    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator();
    }

    #[Test]
    public function generatesSlugFromOsmCityNameGermanMonthAndYear(): void
    {
        $ride = Fixtures::ride('Berlin', dateTime: new Carbon('2026-05-10 15:00', 'Europe/Berlin'))->setSlug(null);

        $this->generator->generateForRide($ride);

        self::assertSame('kidical-mass-berlin-mai-2026', $ride->getSlug());
    }

    #[Test]
    public function returnsTheSameInstance(): void
    {
        $ride = Fixtures::ride();

        self::assertSame($ride, $this->generator->generateForRide($ride));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function cityNameProvider(): iterable
    {
        yield 'umlauts are transliterated' => ['München Giesing Ost', 'kidical-mass-muenchen-giesing-ost-mai-2026'];
        yield 'parens are dropped' => ['Frankfurt (Oder)', 'kidical-mass-frankfurt-oder-mai-2026'];
        yield 'sharp s' => ['Gießen', 'kidical-mass-giessen-mai-2026'];
        yield 'district suffix is kept' => ['Berlin Lichtenberg', 'kidical-mass-berlin-lichtenberg-mai-2026'];
        yield 'slash and spaces' => ['Ravensburg / Weingarten', 'kidical-mass-ravensburg-weingarten-mai-2026'];
        yield 'uppercase' => ['WIEN', 'kidical-mass-wien-mai-2026'];
    }

    #[Test]
    #[DataProvider('cityNameProvider')]
    public function slugifiesTheOsmCityName(string $cityName, string $expectedSlug): void
    {
        $ride = Fixtures::ride($cityName, Fixtures::city('CM', 'cm'), new Carbon('2026-05-10', 'Europe/Berlin'));

        self::assertSame($expectedSlug, $this->generator->generateForRide($ride)->getSlug());
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function monthProvider(): iterable
    {
        yield 'January' => ['2026-01-15', 'kidical-mass-berlin-januar-2026'];
        yield 'June' => ['2026-06-15', 'kidical-mass-berlin-juni-2026'];
        yield 'September' => ['2026-09-15', 'kidical-mass-berlin-september-2026'];
        yield 'December, year boundary' => ['2025-12-31 23:59', 'kidical-mass-berlin-dezember-2025'];
        yield 'March is ASCII-folded' => ['2026-03-15', 'kidical-mass-berlin-maerz-2026'];
    }

    #[Test]
    #[DataProvider('monthProvider')]
    public function usesGermanMonthName(string $date, string $expectedSlug): void
    {
        $ride = Fixtures::ride('Berlin', dateTime: new Carbon($date, 'Europe/Berlin'));

        self::assertSame($expectedSlug, $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function slugOnlyContainsSlugSafeCharacters(): void
    {
        $ride = Fixtures::ride('Gießen', dateTime: new Carbon('2026-03-15', 'Europe/Berlin'));

        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function leavesSlugUntouchedWithoutCity(): void
    {
        $ride = Fixtures::ride()->setCity(null)->setSlug(null);

        self::assertNull($this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function leavesSlugUntouchedWhenCityHasNoMainSlug(): void
    {
        $ride = Fixtures::ride('Berlin', Fixtures::city('Berlin'))->setSlug('pre-existing');

        self::assertSame('pre-existing', $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function leavesSlugUntouchedWithoutDateTime(): void
    {
        $ride = Fixtures::ride()->setDateTime(null)->setSlug(null);

        self::assertNull($this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function overwritesAnExistingSlug(): void
    {
        $ride = Fixtures::ride()->setSlug('old');

        self::assertSame('kidical-mass-berlin-mai-2026', $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function slugDependsOnOsmNameNotOnCmCityName(): void
    {
        $ride = Fixtures::ride('Berlin Pankow', Fixtures::city('Berlin', 'berlin'));

        self::assertSame('kidical-mass-berlin-pankow-mai-2026', $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function monthIsTakenFromRideTimezoneNotUtc(): void
    {
        // 2026-05-31 23:30 in Berlin is already June in... no: it is still May locally, but 21:30 UTC — stays May.
        // 2026-06-01 00:30 Berlin is 2026-05-31 22:30 UTC — local month must win.
        $ride = Fixtures::ride('Berlin', dateTime: new Carbon('2026-06-01 00:30', 'Europe/Berlin'));

        self::assertSame('kidical-mass-berlin-juni-2026', $this->generator->generateForRide($ride)->getSlug());
    }

    #[Test]
    public function doesNotChangeTheLocaleOfTheRideDateTime(): void
    {
        $dateTime = new Carbon('2026-05-10', 'Europe/Berlin');
        $ride = Fixtures::ride('Berlin', dateTime: $dateTime);

        $this->generator->generateForRide($ride);

        self::assertSame('en', $dateTime->locale);
        self::assertSame('May', $dateTime->monthName);
        self::assertSame('kidical-mass-berlin-mai-2026', $ride->getSlug());
    }

    /**
     * Documents known bug #4: two rides of the same OSM city in the same month get the same slug.
     */
    #[Test]
    public function ridesOfSameCityAndMonthCollide(): void
    {
        $first = Fixtures::ride('Wien', dateTime: new Carbon('2026-05-10 10:00', 'Europe/Vienna'))->setDescription('Strecke 1');
        $second = Fixtures::ride('Wien', dateTime: new Carbon('2026-05-10 14:00', 'Europe/Vienna'))->setDescription('Strecke 2');

        $this->generator->generateForRide($first);
        $this->generator->generateForRide($second);

        self::assertSame($first->getSlug(), $second->getSlug());
    }

    #[Test]
    public function knownBugSlugsShouldBeUniquePerRide(): void
    {
        $this->assertKnownBugStillPresent('CLAUDE.md known bug #4, SlugGenerator.php:21', function (): void {
            $first = Fixtures::ride('Wien', dateTime: new Carbon('2026-05-10 10:00', 'Europe/Vienna'))->setDescription('Strecke 1');
            $second = Fixtures::ride('Wien', dateTime: new Carbon('2026-05-10 14:00', 'Europe/Vienna'))->setDescription('Strecke 2');

            $this->generator->generateForRide($first);
            $this->generator->generateForRide($second);

            self::assertNotSame($first->getSlug(), $second->getSlug());
        });
    }

    #[Test]
    public function slugIsAlwaysLowercaseEvenForUppercaseYearFormat(): void
    {
        $ride = new Ride();
        $ride->setCityName('Kiel')->setCity(Fixtures::city('Kiel', 'kiel'))->setDateTime(new Carbon('2027-08-01', 'Europe/Berlin'));

        self::assertSame('kidical-mass-kiel-august-2027', $this->generator->generateForRide($ride)->getSlug());
    }
}
