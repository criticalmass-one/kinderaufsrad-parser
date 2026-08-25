<?php declare(strict_types=1);

namespace App\Tests\CityFetcher;

use App\Model\City;
use App\Tests\Double\GuzzleMockTrait;
use App\Tests\Double\TestableCityFetcher;
use App\Tests\Fixture\Fixtures;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\VarDumper;

final class CityFetcherTest extends TestCase
{
    use GuzzleMockTrait;

    private const string CITY_LIST_JSON = '[{"id":1,"name":"Berlin","timezone":"Europe/Berlin","latitude":52.52,"longitude":13.405,"main_slug":{"id":9,"slug":"berlin"}},{"id":2,"name":"Potsdam","timezone":"Europe/Berlin","latitude":52.4,"longitude":13.06,"main_slug":{"id":10,"slug":"potsdam"}}]';

    /** @var list<mixed> */
    private array $dumped = [];

    protected function setUp(): void
    {
        // CityFetcher::getCityForName() dump()s caught exceptions; keep test output clean and observable.
        VarDumper::setHandler(function (mixed $value): void {
            $this->dumped[] = $value;
        });
    }

    protected function tearDown(): void
    {
        VarDumper::setHandler(null);
    }

    /** @param list<Response|\Throwable> $queue */
    private function fetcher(array $queue): TestableCityFetcher
    {
        $fetcher = new TestableCityFetcher('https://cm.test/');
        $this->injectClient($fetcher, $this->createMockedClient($queue));

        return $fetcher;
    }

    #[Test]
    public function cityListForCoordQueriesApiWithCoordinatesAndFixedRadius(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::CITY_LIST_JSON)]);

        $fetcher->getCityListForCoord(52.52, 13.405);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/city', $request->getUri()->getPath());
        self::assertSame('cm.test', $request->getUri()->getHost());
        self::assertSame(['centerLatitude' => '52.52', 'centerLongitude' => '13.405', 'radius' => '50'], $this->queryOf($request));
    }

    #[Test]
    public function cityListForCoordDeserializesAllCitiesInOrder(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::CITY_LIST_JSON)]);

        $cities = $fetcher->getCityListForCoord(52.52, 13.405);

        self::assertContainsOnlyInstancesOf(City::class, $cities);
        self::assertSame(['Berlin', 'Potsdam'], array_map(static fn(City $c): ?string => $c->getName(), $cities));
        self::assertSame('berlin', $cities[0]->getMainSlug()?->getSlug());
    }

    #[Test]
    public function cityListForCoordIsEmptyForEmptyResponse(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        self::assertSame([], $fetcher->getCityListForCoord(0.0, 0.0));
    }

    #[Test]
    public function cityListForCoordPropagatesServerErrors(): void
    {
        $fetcher = $this->fetcher([new Response(500)]);

        $this->expectException(ServerException::class);

        $fetcher->getCityListForCoord(52.52, 13.405);
    }

    #[Test]
    public function cityForCoordReturnsTheLastCityOfTheList(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::CITY_LIST_JSON)]);

        self::assertSame('Potsdam', $fetcher->getCityForCoord(52.52, 13.405)?->getName());
    }

    #[Test]
    public function cityForCoordReturnsNullWhenNothingIsInRadius(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        self::assertNull($fetcher->getCityForCoord(0.0, 0.0));
    }

    #[Test]
    public function cityForNameQueriesByName(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::CITY_LIST_JSON)]);

        $fetcher->getCityForName('Berlin');

        $request = $this->lastRequest();
        self::assertSame('/api/city', $request->getUri()->getPath());
        self::assertSame(['name' => 'Berlin'], $this->queryOf($request));
    }

    #[Test]
    public function cityForNameReturnsLastMatch(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::CITY_LIST_JSON)]);

        self::assertSame('Potsdam', $fetcher->getCityForName('Berlin')?->getName());
    }

    #[Test]
    public function cityForNameReturnsNullForEmptyList(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        self::assertNull($fetcher->getCityForName('Atlantis'));
        self::assertSame([], $this->dumped);
    }

    #[Test]
    public function cityForNameSwallowsHttpErrorsAndDumpsThem(): void
    {
        $fetcher = $this->fetcher([new Response(500)]);

        self::assertNull($fetcher->getCityForName('Berlin'));
        self::assertCount(1, $this->dumped);
        self::assertInstanceOf(ServerException::class, $this->dumped[0]);
    }

    #[Test]
    public function cityForNameSwallowsInvalidJson(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '{not json')]);

        self::assertNull($fetcher->getCityForName('Berlin'));
        self::assertCount(1, $this->dumped);
    }

    #[Test]
    public function cityForRideUsesTheRideCityName(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        $fetcher->getCityForRide(Fixtures::ride('Potsdam'));

        self::assertSame(['name' => 'Potsdam'], $this->queryOf($this->lastRequest()));
    }

    #[Test]
    public function cityForRideWithoutCityNameFails(): void
    {
        $fetcher = $this->fetcher([]);

        $this->expectException(\TypeError::class);

        $fetcher->getCityForRide((new \App\Model\Ride())->setCityName(null));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function cityNameMappingProvider(): iterable
    {
        yield 'unmapped name is kept' => ['Hamburg', 'Hamburg'];
        yield 'whitespace is trimmed' => ['  Hamburg ', 'Hamburg'];
        yield 'explicit mapping' => ['Frankfurt am Main', 'Frankfurt'];
        yield 'district mapped to city' => ['Berlin Lichtenberg', 'Berlin'];
        yield 'Pankow is its own CM city' => ['Berlin Pankow', 'Pankow'];
        yield 'Ulm is expanded' => ['Ulm', 'Ulm & Neu-Ulm'];
        yield 'country suffix (AU) is removed' => ['Graz (AU)', 'Graz'];
        yield 'country suffix (CH) is removed' => ['Zürich (CH)', 'Zürich'];
        yield 'plain Braunau is mapped' => ['Braunau', 'Braunau am Inn'];
        // Suffix stripping leaves a trailing space and trim() only runs after the lookup,
        // so "Braunau (AU)" misses the "Braunau" mapping key.
        yield 'suffix leaves trailing space that defeats the mapping' => ['Braunau (AU)', 'Braunau'];
        yield 'Wiener Neustadt with suffix hits trailing-space mapping key' => ['Wiener Neustadt (AU)', 'Wien'];
        yield 'Wiener Neustadt without suffix is unmapped' => ['Wiener Neustadt', 'Wiener Neustadt'];
        yield 'Wien Neustadt maps to Wien' => ['Wien Neustadt', 'Wien'];
        yield 'mapping is case sensitive' => ['frankfurt am main', 'frankfurt am main'];
        yield 'Kehl/Strasbourg with FR suffix is not mapped (suffix stripped first)' => ['Kehl am Rhein / Strasbourg (FR)', 'Kehl am Rhein / Strasbourg'];
        yield 'Montevideo suffix is mapped explicitly' => ['Montevideo (Uruguay)', 'Montevideo'];
        yield 'Geneva is translated' => ['Geneva', 'Genf'];
    }

    #[Test]
    #[DataProvider('cityNameMappingProvider')]
    public function fixCityNameAppliesSuffixStrippingAndRenameMap(string $input, string $expected): void
    {
        $fetcher = $this->fetcher([]);

        self::assertSame($expected, $fetcher->exposeFixCityName($input));
    }

    #[Test]
    public function cityForNameSendsTheMappedName(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        $fetcher->getCityForName('Frankfurt am Main');

        self::assertSame(['name' => 'Frankfurt'], $this->queryOf($this->lastRequest()));
    }

    #[Test]
    public function cityForNameEncodesUmlauts(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '[]')]);

        $fetcher->getCityForName('München');

        self::assertSame('name=M%C3%BCnchen', $this->lastRequest()->getUri()->getQuery());
    }
}
