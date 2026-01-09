<?php declare(strict_types=1);

namespace App\Tests;

use App\Model\City;
use App\Model\CitySlug;
use App\Model\Ride;
use App\RidePusher\RidePusher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class RidePusherTest extends TestCase
{
    private function injectMockedClient(RidePusher $pusher, Client $client): void
    {
        $ref = new \ReflectionClass($pusher);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($pusher, $client);
    }

    public function testPostRideSendsCorrectUrlAndBody(): void
    {
        $history = [];

        $mock = new MockHandler([
            new Response(200, [], ''),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => $handlerStack,
        ]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($this->isInstanceOf(Ride::class), 'json')
            ->willReturn('{"slug":"x"}');

        $pusher = new RidePusher($serializer, 'https://example.invalid');
        $this->injectMockedClient($pusher, $client);

        $ride = new Ride();
        $ride->setSlug('kidical-mass-berlin-april-2023');

        $city = new City();
        $city->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));
        $ride->setCity($city);

        $pusher->postRide($ride);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/api/berlin/kidical-mass-berlin-april-2023', (string) $request->getUri());
        $this->assertSame('{"slug":"x"}', (string) $request->getBody());
    }

    public function testPutRideSendsCorrectUrlAndBody(): void
    {
        $history = [];

        $mock = new MockHandler([
            new Response(200, [], ''),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => $handlerStack,
        ]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($this->isInstanceOf(Ride::class), 'json')
            ->willReturn('{"slug":"x"}');

        $pusher = new RidePusher($serializer, 'https://example.invalid');
        $this->injectMockedClient($pusher, $client);

        $ride = new Ride();
        $ride->setSlug('kidical-mass-berlin-april-2023');

        $city = new City();
        $city->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));
        $ride->setCity($city);

        $pusher->putRide($ride);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/api/berlin/kidical-mass-berlin-april-2023', (string) $request->getUri());
        $this->assertSame('{"slug":"x"}', (string) $request->getBody());
    }
}

