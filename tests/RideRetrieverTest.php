<?php declare(strict_types=1);

namespace App\Tests;

use App\Model\City;
use App\Model\CitySlug;
use App\Model\Ride;
use App\RideRetriever\RideRetriever;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RideRetrieverTest extends TestCase
{
    private function injectMockedClient(RideRetriever $retriever, Client $client): void
    {
        $ref = new \ReflectionClass($retriever);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($retriever, $client);
    }

    public function testDoesRideExistReturnsFalseWhenMissingCityOrSlug(): void
    {
        $retriever = new RideRetriever('https://example.invalid');

        $ride = new Ride();
        $ride->setSlug(null);

        $this->assertFalse($retriever->doesRideExist($ride));

        $ride->setSlug('foo');
        $this->assertFalse($retriever->doesRideExist($ride));
    }

    public function testDoesRideExistFallsBackToDateSlugIfNotFound(): void
    {
        $history = [];

        $mock = new MockHandler([
            // 1) Anfrage mit ride->slug: 404
            new ClientException('Not found', new Request('GET', 'test'), new Response(404)),
            // 2) Anfrage mit Y-m-d: 200
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'slug' => '2023-04-12',
                'title' => 'Test',
                'ride_type' => 'KIDICAL_MASS',
                'date_time' => 1681257600,
                'city' => [
                    'id' => 1,
                    'name' => 'Berlin',
                    'timezone' => 'Europe/Berlin',
                    'latitude' => 52.52,
                    'longitude' => 13.405,
                    'main_slug' => ['id' => 1, 'slug' => 'berlin'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => $handlerStack,
        ]);

        $retriever = new RideRetriever('https://example.invalid');
        $this->injectMockedClient($retriever, $client);

        $city = (new City())->setName('Berlin')->setMainSlug((new CitySlug())->setId(1)->setSlug('berlin'));
        $ride = (new Ride())
            ->setCity($city)
            ->setSlug('kidical-mass-berlin-april-2023')
            ->setDateTime(Carbon::parse('2023-04-12'));

        $exists = $retriever->doesRideExist($ride);

        $this->assertTrue($exists);
        $this->assertCount(2, $history);

        $this->assertStringContainsString('/api/berlin/kidical-mass-berlin-april-2023', (string) $history[0]['request']->getUri());
        $this->assertStringContainsString('/api/berlin/2023-04-12', (string) $history[1]['request']->getUri());
    }

    public function testFetchBySlugsReturnsNullOn404(): void
    {
        $mock = new MockHandler([
            new ClientException('Not found', new Request('GET', 'test'), new Response(404)),
        ]);

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => HandlerStack::create($mock),
        ]);

        $retriever = new RideRetriever('https://example.invalid');
        $this->injectMockedClient($retriever, $client);

        $ride = $retriever->fetchBySlugs('berlin', 'does-not-exist');

        $this->assertNull($ride);
    }
}

