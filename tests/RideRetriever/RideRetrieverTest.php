<?php declare(strict_types=1);

namespace App\Tests\RideRetriever;

use App\Model\Ride;
use App\RideRetriever\RideRetriever;
use App\Tests\Double\GuzzleMockTrait;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RideRetrieverTest extends TestCase
{
    use GuzzleMockTrait;

    /** @param list<Response|\Throwable> $queue */
    private function retriever(array $queue): RideRetriever
    {
        $retriever = new RideRetriever('https://cm.test/');
        $this->injectClient($retriever, $this->createMockedClient($queue));

        return $retriever;
    }

    #[Test]
    public function fetchBySlugsRequestsTheRideEndpoint(): void
    {
        $retriever = $this->retriever([new Response(200, [], Fixtures::rideApiJson())]);

        $retriever->fetchBySlugs('berlin', 'kidical-mass-berlin-mai-2026');

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://cm.test/api/berlin/kidical-mass-berlin-mai-2026', (string) $request->getUri());
    }

    #[Test]
    public function fetchBySlugsDeserializesTheRide(): void
    {
        $retriever = $this->retriever([new Response(200, [], Fixtures::rideApiJson())]);

        $ride = $retriever->fetchBySlugs('berlin', 'kidical-mass-berlin-mai-2026');

        self::assertInstanceOf(Ride::class, $ride);
        self::assertSame('kidical-mass-berlin-mai-2026', $ride->getSlug());
        self::assertSame('Berlin', $ride->getCity()?->getName());
        self::assertSame(1778418000, $ride->getDateTime()?->timestamp);
    }

    #[Test]
    public function fetchBySlugsReturnsNullOn404(): void
    {
        $retriever = $this->retriever([new Response(404, [], '{"error":"not found"}')]);

        self::assertNull($retriever->fetchBySlugs('berlin', 'nope'));
    }

    #[Test]
    public function fetchBySlugsPropagatesServerErrors(): void
    {
        $retriever = $this->retriever([new Response(503)]);

        $this->expectException(ServerException::class);

        $retriever->fetchBySlugs('berlin', 'kidical-mass-berlin-mai-2026');
    }

    /**
     * Bug: an API ride without ride_type (the API returns null for rides whose type is
     * unset) cannot be deserialized because Ride::setRideType() is not nullable.
     */
    #[Test]
    public function fetchBySlugsFailsForRidesWithoutRideType(): void
    {
        $retriever = $this->retriever([new Response(200, [], Fixtures::rideApiJson(['ride_type' => null]))]);

        $this->expectException(\TypeError::class);

        $retriever->fetchBySlugs('berlin', 'kidical-mass-berlin-mai-2026');
    }

    #[Test]
    public function doesRideExistIsFalseWithoutCity(): void
    {
        $retriever = $this->retriever([]);

        self::assertFalse($retriever->doesRideExist(Fixtures::ride()->setCity(null)));
        self::assertSame([], $this->sentRequests());
    }

    #[Test]
    public function doesRideExistIsFalseWithoutSlug(): void
    {
        $retriever = $this->retriever([]);

        self::assertFalse($retriever->doesRideExist(Fixtures::ride()->setSlug(null)));
        self::assertSame([], $this->sentRequests());
    }

    #[Test]
    public function doesRideExistIsTrueWhenSlugLookupSucceeds(): void
    {
        $retriever = $this->retriever([new Response(200, [], Fixtures::rideApiJson())]);

        self::assertTrue($retriever->doesRideExist(Fixtures::ride()));
        self::assertCount(1, $this->sentRequests());
    }

    #[Test]
    public function doesRideExistFallsBackToDateLookup(): void
    {
        $retriever = $this->retriever([
            new Response(404),
            new Response(200, [], Fixtures::rideApiJson(['slug' => '2026-05-10'])),
        ]);

        $ride = Fixtures::ride(dateTime: new Carbon('2026-05-10 15:00', 'Europe/Berlin'));

        self::assertTrue($retriever->doesRideExist($ride));

        $paths = array_map(static fn($request): string => $request->getUri()->getPath(), $this->sentRequests());
        self::assertSame(['/api/berlin/kidical-mass-berlin-mai-2026', '/api/berlin/2026-05-10'], $paths);
    }

    #[Test]
    public function doesRideExistIsFalseWhenBothLookupsMiss(): void
    {
        $retriever = $this->retriever([new Response(404), new Response(404)]);

        self::assertFalse($retriever->doesRideExist(Fixtures::ride()));
        self::assertCount(2, $this->sentRequests());
    }

    #[Test]
    public function doesRideExistSkipsDateFallbackWithoutDateTime(): void
    {
        $retriever = $this->retriever([new Response(404)]);

        self::assertFalse($retriever->doesRideExist(Fixtures::ride()->setDateTime(null)));
        self::assertCount(1, $this->sentRequests());
    }

    #[Test]
    public function doesRideExistFailsWhenCityHasNoMainSlug(): void
    {
        $retriever = $this->retriever([]);
        $ride = Fixtures::ride('Berlin', Fixtures::city('Berlin'));

        $this->expectException(\Error::class);

        $retriever->doesRideExist($ride);
    }

    #[Test]
    public function fetchOriginalRideUsesCitySlugAndRideSlug(): void
    {
        $retriever = $this->retriever([new Response(200, [], Fixtures::rideApiJson())]);
        $ride = Fixtures::ride('Wien', Fixtures::city('Wien', 'vienna'))->setSlug('kidical-mass-wien-mai-2026');

        $original = $retriever->fetchOriginalRide($ride);

        self::assertNotNull($original);
        self::assertSame('/api/vienna/kidical-mass-wien-mai-2026', $this->lastRequest()->getUri()->getPath());
    }

    #[Test]
    public function fetchOriginalRideReturnsNullWhenMissing(): void
    {
        $retriever = $this->retriever([new Response(404)]);

        self::assertNull($retriever->fetchOriginalRide(Fixtures::ride()));
    }
}
