<?php declare(strict_types=1);

namespace App\Tests\RidePusher;

use App\RidePusher\RidePusher;
use App\Serializer\Normalizer\RideNormalizer;
use App\Tests\Double\GuzzleMockTrait;
use App\Tests\Fixture\Fixtures;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class RidePusherTest extends TestCase
{
    use GuzzleMockTrait;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    /** @param list<Response|\Throwable> $queue */
    private function pusher(array $queue, ?SerializerInterface $serializer = null): RidePusher
    {
        $serializer ??= new Serializer([new RideNormalizer()], [new JsonEncoder()]);

        $pusher = new RidePusher($serializer, 'https://cm.test/');
        $this->injectClient($pusher, $this->createMockedClient($queue));

        return $pusher;
    }

    #[Test]
    public function putRideSendsPutToCityAndRideSlug(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        self::assertSame($pusher, $pusher->putRide(Fixtures::ride()));

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('https://cm.test/api/berlin/kidical-mass-berlin-mai-2026', (string) $request->getUri());
    }

    #[Test]
    public function postRideSendsPostToCityAndRideSlug(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        self::assertSame($pusher, $pusher->postRide(Fixtures::ride()));

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/api/berlin/kidical-mass-berlin-mai-2026', $request->getUri()->getPath());
    }

    #[Test]
    public function bodyIsTheSerializedRide(): void
    {
        $pusher = $this->pusher([new Response(200)]);
        $ride = Fixtures::ride()->setCreatedAt(new Carbon('2026-03-01 08:00:00', 'UTC'));

        $pusher->putRide($ride);

        $body = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('kidical-mass-berlin-mai-2026', $body['slug']);
        self::assertSame('Kidical Mass Berlin 10.05.2026', $body['title']);
        self::assertSame(1778418000, $body['date_time']);
        self::assertSame(52.52, $body['latitude']);
        self::assertSame(13.405, $body['longitude']);
        self::assertSame('Rathausplatz', $body['location']);
        self::assertSame('Familienfreundliche Route', $body['description']);
        self::assertSame(1772352000, $body['created_at']);
        self::assertNull($body['updated_at']);
        self::assertArrayNotHasKey('city', $body);
    }

    #[Test]
    public function usesTheInjectedSerializer(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->with(self::isInstanceOf(\App\Model\Ride::class), 'json')->willReturn('{"custom":true}');

        $pusher = $this->pusher([new Response(200)], $serializer);
        $pusher->postRide(Fixtures::ride());

        self::assertSame('{"custom":true}', (string) $this->lastRequest()->getBody());
    }

    /**
     * criticalmass.in deserializes ride_type through BackedEnumNormalizer into RideTypeEnum
     * (since 2026-05-11), so the payload has to carry the enum's backing value.
     */
    #[Test]
    public function payloadCarriesRideTypeAsEnumValue(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        $pusher->putRide(Fixtures::ride());

        $body = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('KIDICAL_MASS', $body['ride_type']);
    }

    /**
     * API quirk: PUT silently overwrites an existing slug (200, never 409). The pusher does
     * not pre-check with GET, so every PUT is a potential overwrite of an existing ride.
     */
    #[Test]
    public function putDoesNotCheckForExistingRideFirst(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        $pusher->putRide(Fixtures::ride());

        $methods = array_map(static fn($request): string => $request->getMethod(), $this->sentRequests());
        self::assertSame(['PUT'], $methods, 'exactly one request, no preceding GET');
    }

    #[Test]
    public function clientErrorsBubbleUp(): void
    {
        $pusher = $this->pusher([new Response(404)]);

        $this->expectException(ClientException::class);

        $pusher->postRide(Fixtures::ride());
    }

    #[Test]
    public function serverErrorsBubbleUp(): void
    {
        $pusher = $this->pusher([new Response(500, [], 'ride_type not settable')]);

        $this->expectException(ServerException::class);

        $pusher->putRide(Fixtures::ride());
    }

    #[Test]
    public function rideWithoutCityCannotBePushed(): void
    {
        $pusher = $this->pusher([]);

        $this->expectException(\Error::class);

        $pusher->putRide(Fixtures::ride()->setCity(null));
    }

    #[Test]
    public function rideWhoseCityHasNoMainSlugCannotBePushed(): void
    {
        $pusher = $this->pusher([]);

        $this->expectException(\Error::class);

        $pusher->putRide(Fixtures::ride('Berlin', Fixtures::city('Berlin')));
    }

    #[Test]
    public function rideWithoutSlugTargetsAnEmptySegment(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        $pusher->putRide(Fixtures::ride()->setSlug(null));

        self::assertSame('/api/berlin/', $this->lastRequest()->getUri()->getPath());
    }

    #[Test]
    public function noAuthorizationHeaderIsSent(): void
    {
        $pusher = $this->pusher([new Response(200)]);

        $pusher->putRide(Fixtures::ride());

        self::assertFalse($this->lastRequest()->hasHeader('Authorization'));
    }
}
