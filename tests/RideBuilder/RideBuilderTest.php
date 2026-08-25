<?php declare(strict_types=1);

namespace App\Tests\RideBuilder;

use App\CityFetcher\CityFetcherInterface;
use App\Model\Ride;
use App\RideBuilder\RideBuilder;
use App\RideBuilder\SlugGenerator;
use App\RideBuilder\SlugGeneratorInterface;
use App\Tests\Double\InMemoryCityFetcher;
use App\Tests\Double\KnownBugTrait;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RideBuilderTest extends TestCase
{
    use KnownBugTrait;

    private InMemoryCityFetcher $cityFetcher;
    private RideBuilder $builder;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'Europe/Berlin'));

        $this->cityFetcher = new InMemoryCityFetcher();
        $this->builder = new RideBuilder($this->cityFetcher, new SlugGenerator());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    #[Test]
    public function buildsCompleteRideFromWellFormedFeature(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);

        $feature = Fixtures::feature([
            'name' => 'Berlin',
            'Datum' => '10.05.2026',
            'Zeit' => '15:00 Uhr',
            'Start' => 'Brandenburger Tor',
            'Besonderheit' => 'Sternfahrt aus allen Bezirken',
        ], 52.5163, 13.3777);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertInstanceOf(Ride::class, $ride);
        self::assertSame('Berlin', $ride->getCityName());
        self::assertSame('Berlin', $ride->getCity()?->getName());
        self::assertSame(52.5163, $ride->getLatitude());
        self::assertSame(13.3777, $ride->getLongitude());
        self::assertSame('2026-05-10 15:00:00 Europe/Berlin', $ride->getDateTime()?->format('Y-m-d H:i:s e'));
        self::assertSame('Brandenburger Tor', $ride->getLocation());
        self::assertSame('Sternfahrt aus allen Bezirken', $ride->getDescription());
        self::assertSame('Kidical Mass Berlin 10.05.2026', $ride->getTitle());
        self::assertSame('KIDICAL_MASS', $ride->getRideType());
        self::assertSame('kidical-mass-berlin-mai-2026', $ride->getSlug());
    }

    #[Test]
    public function coordinatesAreReadAsLongitudeLatitudePairAndPassedToCityLookup(): void
    {
        $feature = Fixtures::feature(['name' => 'Hamburg', 'Datum' => '10.05.2026', 'Zeit' => '14:00'], 53.55, 9.99);

        $this->builder->buildFromFeature($feature);

        self::assertSame([[53.55, 9.99]], $this->cityFetcher->coordLookups());
    }

    #[Test]
    public function returnsNullWhenDatumIsMissing(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Zeit' => '15:00']);

        self::assertNull($this->builder->buildFromFeature($feature));
    }

    #[Test]
    public function returnsNullWhenZeitIsMissing(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026']);

        self::assertNull($this->builder->buildFromFeature($feature));
    }

    #[Test]
    public function returnsNullWhenZeitIsNull(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => null]);

        self::assertNull($this->builder->buildFromFeature($feature));
    }

    #[Test]
    public function emptyZeitFallsBackToMidnight(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertSame('2026-05-10 00:00', $ride?->getDateTime()?->format('Y-m-d H:i'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function parseableZeitProvider(): iterable
    {
        yield 'HH:MM' => ['15:00', '15:00'];
        yield 'HH:MM Uhr' => ['15:00 Uhr', '15:00'];
        yield 'HH:MMUhr without space' => ['15:00Uhr', '15:00'];
        yield 'HH.MM Uhr' => ['15.00 Uhr', '15:00'];
        yield 'H:MM' => ['9:30', '09:30'];
        yield 'bare hour with Uhr' => ['15 Uhr', '15:00'];
        yield 'bare hour' => ['15', '15:00'];
        yield 'folgt placeholder' => ['folgt', '00:00'];
        yield 'Uhrzeit folgt placeholder' => ['Uhrzeit folgt', '00:00'];
        yield 'empty parens' => ['15:00 ()', '15:00'];
        yield 'time with range in parens' => ['11:00 (11:00 - 12:30)', '11:00'];
        yield 'open ended range in parens' => ['08:00 (00:30 - )', '08:00'];
        yield 'only range in parens' => [' (11:00 - 12:30)', '11:00'];
        yield 'only open ended range in parens' => ['(14:00 -)', '14:00'];
        yield 'time range with dash' => ['15:00 - 17:00', '15:00'];
        yield 'hour range with Uhr' => ['15 - 17 Uhr', '15:00'];
        yield 'ca. prefix' => ['ca. 14:30 Uhr', '14:30'];
    }

    #[Test]
    #[DataProvider('parseableZeitProvider')]
    public function parsesSupportedZeitFormats(string $zeit, string $expectedTime): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => $zeit]);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertNotNull($ride, sprintf('Zeit "%s" should be parseable', $zeit));
        self::assertSame('2026-05-10 ' . $expectedTime, $ride->getDateTime()?->format('Y-m-d H:i'));
    }

    /** @return iterable<string, array{0: string}> */
    public static function unparseableZeitProvider(): iterable
    {
        yield 'digits without a time' => ['2026'];
        yield 'out of range hour' => ['25:00'];
    }

    #[Test]
    #[DataProvider('unparseableZeitProvider')]
    public function rideIsDroppedWhenZeitContainsDigitsButNoTime(string $zeit): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => $zeit]);

        self::assertNull($this->builder->buildFromFeature($feature));
    }

    #[Test]
    public function usesTimezoneOfMatchedCity(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Montevideo', 'montevideo', timezone: 'America/Montevideo')]);
        $feature = Fixtures::feature(['name' => 'Montevideo', 'Datum' => '10.05.2026', 'Zeit' => '15:00'], -34.9, -56.2);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertSame('America/Montevideo', $ride?->getDateTime()?->getTimezone()->getName());
        self::assertSame('2026-05-10T15:00:00-03:00', $ride?->getDateTime()?->format('c'));
    }

    #[Test]
    public function fallsBackToEuropeBerlinWhenNoCityMatched(): void
    {
        $feature = Fixtures::feature(['name' => 'Nowhere', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertNull($ride?->getCity());
        self::assertSame('Europe/Berlin', $ride?->getDateTime()?->getTimezone()->getName());
        self::assertSame('2026-05-10T15:00:00+02:00', $ride?->getDateTime()?->format('c'));
    }

    #[Test]
    public function unmatchedCityLeavesSlugEmptyButKeepsTitle(): void
    {
        $feature = Fixtures::feature(['name' => 'Nowhere', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertNotNull($ride);
        self::assertFalse($ride->hasSlug());
        self::assertSame('Kidical Mass Nowhere 10.05.2026', $ride->getTitle());
    }

    #[Test]
    public function cityNameIsTrimmedAndFallsBackToCapitalisedNameProperty(): void
    {
        $feature = Fixtures::feature(['Name' => '  Köln ', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertSame('Köln', $ride?->getCityName());
        self::assertSame('Kidical Mass Köln 10.05.2026', $ride?->getTitle());
    }

    #[Test]
    public function lowercaseNamePropertyWinsOverCapitalisedOne(): void
    {
        $feature = Fixtures::feature(['name' => 'Bonn', 'Name' => 'Köln', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertSame('Bonn', $this->builder->buildFromFeature($feature)?->getCityName());
    }

    #[Test]
    public function matchesCityWhoseNameIsContainedInOsmName(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $feature = Fixtures::feature(['name' => 'Berlin Lichtenberg', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertSame('Berlin', $ride?->getCity()?->getName());
        self::assertSame('Berlin Lichtenberg', $ride?->getCityName());
        self::assertSame('kidical-mass-berlin-lichtenberg-mai-2026', $ride?->getSlug());
    }

    #[Test]
    public function firstMatchingCityInListOrderWins(): void
    {
        $this->cityFetcher->returnForCoord([
            Fixtures::city('Neu-Ulm', 'neu-ulm', 2),
            Fixtures::city('Ulm', 'ulm', 1),
        ]);
        $feature = Fixtures::feature(['name' => 'Ulm & Neu-Ulm', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertSame('Neu-Ulm', $this->builder->buildFromFeature($feature)?->getCity()?->getName());
    }

    #[Test]
    public function nonMatchingCitiesInRadiusAreIgnored(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Potsdam', 'potsdam'), Fixtures::city('Bernau', 'bernau')]);
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertNull($this->builder->buildFromFeature($feature)?->getCity());
    }

    #[Test]
    public function cityMatchIsCaseSensitive(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $feature = Fixtures::feature(['name' => 'berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertNull($this->builder->buildFromFeature($feature)?->getCity());
    }

    /**
     * Documents known bug #5: the substring match has no word boundaries,
     * so "Wiener Neustadt" is attributed to the CM city "Wien".
     */
    #[Test]
    public function substringMatchWithoutWordBoundariesAttributesWienerNeustadtToWien(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Wien', 'wien', timezone: 'Europe/Vienna')]);
        $feature = Fixtures::feature(['name' => 'Wiener Neustadt', 'Datum' => '10.05.2026', 'Zeit' => '15:00'], 47.8, 16.25);

        self::assertSame('Wien', $this->builder->buildFromFeature($feature)?->getCity()?->getName());
    }

    #[Test]
    public function knownBugCityMatchShouldRespectWordBoundaries(): void
    {
        $this->assertKnownBugStillPresent('CLAUDE.md known bug #5, RideBuilder.php:37', function (): void {
            $this->cityFetcher->returnForCoord([Fixtures::city('Wien', 'wien')]);
            $feature = Fixtures::feature(['name' => 'Wiener Neustadt', 'Datum' => '10.05.2026', 'Zeit' => '15:00'], 47.8, 16.25);

            self::assertNull($this->builder->buildFromFeature($feature)?->getCity());
        });
    }

    #[Test]
    public function cityWithEmptyNameMatchesEveryFeature(): void
    {
        // strpos($haystack, '') === 0 on PHP 8 — a CM city without a name would match anything.
        $this->cityFetcher->returnForCoord([Fixtures::city('', 'anon')]);
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertSame('', $this->builder->buildFromFeature($feature)?->getCity()?->getName());
    }

    #[Test]
    public function cityIsResolvedEvenWhenRideIsDroppedForMissingDate(): void
    {
        // The coordinate lookup happens before the Datum/Zeit guard, i.e. one API call per feature.
        $feature = Fixtures::feature(['name' => 'Berlin']);

        self::assertNull($this->builder->buildFromFeature($feature));
        self::assertCount(1, $this->cityFetcher->coordLookups());
    }

    #[Test]
    public function missingStartAndBesonderheitLeaveLocationAndDescriptionNull(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        $ride = $this->builder->buildFromFeature($feature);

        self::assertNull($ride?->getLocation());
        self::assertNull($ride?->getDescription());
    }

    #[Test]
    public function slugGeneratorReceivesTheFullyPopulatedRide(): void
    {
        $slugGenerator = $this->createMock(SlugGeneratorInterface::class);
        $slugGenerator->expects(self::once())
            ->method('generateForRide')
            ->with(self::callback(static fn(Ride $ride): bool => $ride->getTitle() === 'Kidical Mass Berlin 10.05.2026' && $ride->getRideType() === 'KIDICAL_MASS'))
            ->willReturnCallback(static fn(Ride $ride): Ride => $ride->setSlug('custom-slug'));

        $builder = new RideBuilder($this->cityFetcher, $slugGenerator);
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertSame('custom-slug', $builder->buildFromFeature($feature)?->getSlug());
    }

    #[Test]
    public function invalidDatumDropsTheRide(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => 'irgendwann', 'Zeit' => '15:00']);

        self::assertNull($this->builder->buildFromFeature($feature));
    }

    #[Test]
    public function isoDatumIsAcceptedToo(): void
    {
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '2026-05-10', 'Zeit' => '15:00']);

        self::assertSame('2026-05-10 15:00', $this->builder->buildFromFeature($feature)?->getDateTime()?->format('Y-m-d H:i'));
    }

    #[Test]
    public function builderOnlyDependsOnTheCityFetcherInterface(): void
    {
        $cityFetcher = $this->createStub(CityFetcherInterface::class);
        $cityFetcher->method('getCityListForCoord')->willReturn([Fixtures::city('Berlin', 'berlin')]);

        $builder = new RideBuilder($cityFetcher, new SlugGenerator());
        $feature = Fixtures::feature(['name' => 'Berlin', 'Datum' => '10.05.2026', 'Zeit' => '15:00']);

        self::assertSame('Berlin', $builder->buildFromFeature($feature)?->getCity()?->getName());
    }
}
