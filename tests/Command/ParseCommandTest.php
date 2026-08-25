<?php declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ParseCommand;
use App\RideBuilder\RideBuilder;
use App\RideBuilder\SlugGenerator;
use App\Tests\Double\InMemoryCityFetcher;
use App\Tests\Double\InMemoryRideRetriever;
use App\Tests\Double\KnownBugTrait;
use App\Tests\Double\RecordingRidePusher;
use App\Tests\Double\UMapResponses;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the real pipeline (ParseCommand -> RideBuilder -> SlugGenerator) with in-memory
 * doubles for every HTTP boundary: UMap (file_get_contents override), city lookup,
 * ride existence check and the pusher. Nothing ever leaves the process.
 */
final class ParseCommandTest extends TestCase
{
    use KnownBugTrait;

    private const string MAP_ID = 'abc123';
    private const string MAP_URL = 'https://umap.openstreetmap.fr/de/datalayer/abc123/';

    private InMemoryCityFetcher $cityFetcher;
    private InMemoryRideRetriever $rideRetriever;
    private RecordingRidePusher $ridePusher;
    private CommandTester $tester;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'Europe/Berlin'));
        UMapResponses::reset();

        $this->cityFetcher = new InMemoryCityFetcher();
        $this->rideRetriever = new InMemoryRideRetriever();
        $this->ridePusher = new RecordingRidePusher();

        $command = new ParseCommand(new RideBuilder($this->cityFetcher, new SlugGenerator()), $this->rideRetriever, $this->ridePusher);
        $this->tester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        UMapResponses::reset();
        Carbon::setTestNow();
    }

    /** @param list<\stdClass> $features */
    private function givenLayer(array $features): void
    {
        UMapResponses::register(self::MAP_URL, Fixtures::featureCollectionJson($features));
    }

    /**
     * Runs the command interactively, answering the "post?" question (default: "n").
     *
     * @param array<string, mixed> $options
     */
    private function runCommand(array $options = [], string $answer = 'n'): int
    {
        $this->tester->setInputs([$answer]);

        return $this->tester->execute(array_merge(['map-identifier' => self::MAP_ID], $options));
    }

    /** Whitespace-normalised output (SymfonyStyle wraps long lines at the terminal width). */
    private function display(): string
    {
        return (string) preg_replace('/\s+/', ' ', $this->tester->getDisplay());
    }

    private static function berlinFeature(string $name = 'Berlin', string $datum = '10.05.2026', string $zeit = '15:00'): \stdClass
    {
        return Fixtures::feature(['name' => $name, 'Datum' => $datum, 'Zeit' => $zeit, 'Start' => 'Brandenburger Tor', 'Besonderheit' => 'Sternfahrt']);
    }

    #[Test]
    public function fetchesTheDatalayerForTheGivenIdentifier(): void
    {
        $this->givenLayer([]);

        self::assertSame(Command::SUCCESS, $this->runCommand());
        self::assertSame([self::MAP_URL], UMapResponses::requestedUrls());
    }

    #[Test]
    public function listsRidesInATableAndDoesNotPushWithoutConfirmation(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature()]);

        $this->runCommand();

        $display = $this->display();
        self::assertStringContainsString('Kidical Mass Berlin 10.05.2026', $display);
        self::assertStringContainsString('kidical-mass-berlin-mai-2026', $display);
        self::assertStringContainsString('2026-05-10 15:00', $display);
        self::assertStringContainsString('Brandenburger Tor', $display);
        self::assertStringContainsString('Sternfahrt', $display);
        self::assertStringContainsString('Should I post those 1 rides to critical mass api?', $display);
        self::assertSame(0, $this->ridePusher->pushCount());
    }

    #[Test]
    public function nonInteractiveRunDefaultsToNotPushing(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature()]);

        $exitCode = $this->tester->execute(['map-identifier' => self::MAP_ID], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('kidical-mass-berlin-mai-2026', $this->display());
        self::assertStringNotContainsString('Should I post', $this->display(), 'the question is not even rendered without a TTY');
        self::assertSame(0, $this->ridePusher->pushCount());
    }

    #[Test]
    public function unmatchedCityIsShownWithQuestionMark(): void
    {
        $this->givenLayer([self::berlinFeature('Atlantis')]);

        $this->runCommand();

        self::assertStringContainsString('Atlantis?', $this->display());
    }

    #[Test]
    public function ridesAreSortedByCityName(): void
    {
        $this->givenLayer([
            self::berlinFeature('Zwickau'),
            self::berlinFeature('Aachen'),
            self::berlinFeature('Mainz'),
        ]);

        $this->runCommand();

        $display = $this->display();
        self::assertLessThan(strpos($display, 'Kidical Mass Mainz'), strpos($display, 'Kidical Mass Aachen'));
        self::assertLessThan(strpos($display, 'Kidical Mass Zwickau'), strpos($display, 'Kidical Mass Mainz'));
    }

    #[Test]
    public function featuresWithoutDateAreSkipped(): void
    {
        $this->givenLayer([
            Fixtures::feature(['name' => 'Ohne Datum', 'Zeit' => '15:00']),
            self::berlinFeature('Mit Datum'),
        ]);

        $this->runCommand();

        $display = $this->display();
        self::assertStringNotContainsString('Ohne Datum', $display);
        self::assertStringContainsString('Should I post those 1 rides', $display);
    }

    #[Test]
    public function featuresWithUnparseableTimeAreSilentlySkipped(): void
    {
        $this->givenLayer([self::berlinFeature('Berlin', zeit: '15:00 ()')]);

        $this->runCommand();

        self::assertStringContainsString('Should I post those 0 rides', $this->display());
        self::assertStringNotContainsString('Berlin', $this->display());
    }

    #[Test]
    public function capitalisedNamePropertyIsPreferredForDeduplication(): void
    {
        $this->givenLayer([
            Fixtures::feature(['Name' => 'Bonn', 'name' => 'Köln', 'Datum' => '10.05.2026', 'Zeit' => '15:00']),
        ]);

        $this->runCommand();

        // ParseCommand dedups by "Name ?? name", RideBuilder names the ride by "name ?? Name".
        self::assertStringContainsString('Kidical Mass Köln', $this->display());
    }

    #[Test]
    public function namesAreTrimmedBeforeDeduplication(): void
    {
        $this->givenLayer([self::berlinFeature('Berlin '), self::berlinFeature(' Berlin')]);

        $this->runCommand();

        self::assertStringContainsString('Should I post those 1 rides', $this->display());
    }

    /**
     * Documents known bug #1: features are indexed by md5(name); the last feature with a
     * given name wins, all others are dropped before they are even built.
     */
    #[Test]
    public function sameNameFeaturesCollapseToTheLastOne(): void
    {
        $this->givenLayer([
            self::berlinFeature('Wien', '09.05.2026', '10:00'),
            self::berlinFeature('Wien', '10.05.2026', '14:00'),
        ]);

        $this->runCommand();

        $display = $this->display();
        self::assertStringContainsString('Should I post those 1 rides', $display);
        self::assertStringContainsString('2026-05-10 14:00', $display);
        self::assertStringNotContainsString('2026-05-09', $display);
    }

    #[Test]
    public function knownBugSameNameDifferentDateShouldYieldSeparateRides(): void
    {
        $this->assertKnownBugStillPresent('CLAUDE.md known bug #1, ParseCommand.php:55', function (): void {
            $this->givenLayer([
                self::berlinFeature('Wien', '09.05.2026', '10:00'),
                self::berlinFeature('Wien', '10.05.2026', '14:00'),
            ]);

            $this->runCommand();

            self::assertStringContainsString('Should I post those 2 rides', $this->display());
        });
    }

    #[Test]
    public function unexistingOnlyFiltersRidesKnownToTheApi(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->rideRetriever->markExisting('kidical-mass-berlin-mai-2026');
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Berlin Pankow')]);

        $this->runCommand(['--unexisting-only' => true]);

        $display = $this->display();
        self::assertStringContainsString('Should I post those 1 rides', $display);
        self::assertStringContainsString('kidical-mass-berlin-pankow-mai-2026', $display);
        self::assertStringNotContainsString('Kidical Mass Berlin 10.05.2026', $display);
        self::assertEqualsCanonicalizing(['kidical-mass-berlin-mai-2026', 'kidical-mass-berlin-pankow-mai-2026'], $this->rideRetriever->checkedSlugs);
    }

    #[Test]
    public function withoutUnexistingOnlyTheRetrieverIsNeverAsked(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin')]);

        $this->runCommand();

        self::assertSame([], $this->rideRetriever->checkedSlugs);
    }

    #[Test]
    public function existingCityOnlyKeepsRidesWithMatchedCity(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Atlantis')]);

        $this->runCommand(['--existing-city-only' => true]);

        $display = $this->display();
        self::assertStringContainsString('Should I post those 1 rides', $display);
        self::assertStringNotContainsString('Atlantis', $display);
    }

    #[Test]
    public function nonExistingCityOnlyKeepsRidesWithoutMatchedCity(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Atlantis')]);

        $this->runCommand(['--non-existing-city-only' => true]);

        $display = $this->display();
        self::assertStringContainsString('Should I post those 1 rides', $display);
        self::assertStringContainsString('Atlantis?', $display);
        self::assertStringNotContainsString('Kidical Mass Berlin', $display);
    }

    #[Test]
    public function bothCityFiltersTogetherLeaveNothing(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Atlantis')]);

        $this->runCommand(['--existing-city-only' => true, '--non-existing-city-only' => true]);

        self::assertStringContainsString('Should I post those 0 rides', $this->display());
    }

    #[Test]
    public function cityFilterMatchesTheCmCityNameExactly(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Berlin Pankow')]);

        $this->runCommand(['--city-filter' => 'Berlin']);

        // Both OSM features map to CM city "Berlin", so both pass the filter.
        self::assertStringContainsString('Should I post those 2 rides', $this->display());
    }

    #[Test]
    public function cityFilterWithNoMatchYieldsEmptyList(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin')]);

        $this->runCommand(['--city-filter' => 'Hamburg']);

        self::assertStringContainsString('Should I post those 0 rides', $this->display());
    }

    /**
     * Bug: --city-filter dereferences getCity() without a null check, so any ride whose
     * city could not be matched crashes the command.
     */
    #[Test]
    public function cityFilterCrashesOnRidesWithoutMatchedCity(): void
    {
        $this->givenLayer([self::berlinFeature('Atlantis')]);

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/getName\(\) on null/');

        $this->runCommand(['--city-filter' => 'Berlin']);
    }

    #[Test]
    public function knownBugCityFilterShouldSkipRidesWithoutCity(): void
    {
        $this->assertKnownBugStillPresent('ParseCommand.php:85 getCity()->getName() on null city', function (): void {
            $this->givenLayer([self::berlinFeature('Atlantis')]);

            $this->runCommand(['--city-filter' => 'Berlin']);

            self::assertStringContainsString('Should I post those 0 rides', $this->display());
        });
    }

    #[Test]
    public function confirmingPutsEveryListedRide(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Berlin Pankow')]);

        $this->runCommand(answer: 'y');

        self::assertCount(2, $this->ridePusher->putRides);
        self::assertCount(0, $this->ridePusher->postedRides);
        self::assertSame(
            ['kidical-mass-berlin-mai-2026', 'kidical-mass-berlin-pankow-mai-2026'],
            array_map(static fn($ride): ?string => $ride->getSlug(), $this->ridePusher->putRides),
        );
    }

    #[Test]
    public function confirmingWithUpdateOptionPostsInsteadOfPuts(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin')]);

        $this->runCommand(['--update' => true], answer: 'y');

        self::assertCount(1, $this->ridePusher->postedRides);
        self::assertCount(0, $this->ridePusher->putRides);
    }

    #[Test]
    public function failedUpdateIsReportedAndDoesNotStopTheRun(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->rideRetriever->markExisting();
        $this->ridePusher->failPostFor('kidical-mass-berlin-mai-2026', new \RuntimeException('404'));
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Berlin Pankow')]);

        $exitCode = $this->runCommand(['--update' => true], answer: 'y');

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('not found', $this->display());
        self::assertCount(1, $this->ridePusher->postedRides);
        self::assertSame('kidical-mass-berlin-pankow-mai-2026', $this->ridePusher->postedRides[0]->getSlug());
    }

    #[Test]
    public function onlyExactYesAnswerTriggersPushing(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin')]);

        $this->runCommand(answer: 'yes');

        self::assertSame(0, $this->ridePusher->pushCount());
    }

    #[Test]
    public function filteredOutRidesAreNotPushed(): void
    {
        $this->cityFetcher->returnForCoord([Fixtures::city('Berlin', 'berlin')]);
        $this->givenLayer([self::berlinFeature('Berlin'), self::berlinFeature('Atlantis')]);

        $this->runCommand(['--existing-city-only' => true], answer: 'y');

        self::assertCount(1, $this->ridePusher->putRides);
        self::assertSame('Berlin', $this->ridePusher->putRides[0]->getCityName());
    }

    #[Test]
    public function invalidJsonFromUmapFails(): void
    {
        UMapResponses::register(self::MAP_URL, '{not json');

        $this->expectException(\JsonException::class);

        $this->runCommand();
    }

    #[Test]
    public function mapIdentifierIsRequired(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

        $this->tester->execute([], ['interactive' => false]);
    }
}
