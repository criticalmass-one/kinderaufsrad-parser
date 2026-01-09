<?php declare(strict_types=1);

namespace App\Tests;

use App\CityFetcher\CityFetcher;
use App\Model\City;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CityFetcherTest extends TestCase
{
    private function buildFetcherWithMockedHttp(array $responses): CityFetcher
    {
        $fetcher = new CityFetcher('https://example.invalid');

        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => $handlerStack,
        ]);

        // CityFetcher konstruiert den Client intern; für Tests ersetzen wir ihn via Reflection.
        $ref = new \ReflectionClass($fetcher);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($fetcher, $client);

        return $fetcher;
    }

    public function testGetCityForNameReturnsNullOnEmptyList(): void
    {
        $fetcher = $this->buildFetcherWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], '[]'),
        ]);

        $city = $fetcher->getCityForName('Berlin');

        $this->assertNull($city);
    }

    public function testGetCityForNameAppliesMappingAndReturnsCity(): void
    {
        $payload = json_encode([
            [
                'id' => 1,
                'name' => 'Ulm & Neu-Ulm',
                'timezone' => 'Europe/Berlin',
                'latitude' => 48.4,
                'longitude' => 9.99,
                'main_slug' => ['id' => 10, 'slug' => 'ulm-neu-ulm'],
            ],
        ], JSON_THROW_ON_ERROR);

        $fetcher = $this->buildFetcherWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], $payload),
        ]);

        $city = $fetcher->getCityForName('Ulm');

        $this->assertInstanceOf(City::class, $city);
        $this->assertSame('Ulm & Neu-Ulm', $city->getName());
        $this->assertSame('Europe/Berlin', $city->getTimezone());
        $this->assertNotNull($city->getMainSlug());
        $this->assertSame('ulm-neu-ulm', $city->getMainSlug()->getSlug());
    }

    public function testGetCityListForCoordDeserializesCityArray(): void
    {
        $payload = json_encode([
            [
                'id' => 2,
                'name' => 'Berlin',
                'timezone' => 'Europe/Berlin',
                'latitude' => 52.52,
                'longitude' => 13.405,
            ],
        ], JSON_THROW_ON_ERROR);

        $fetcher = $this->buildFetcherWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], $payload),
        ]);

        $list = $fetcher->getCityListForCoord(52.52, 13.405);

        $this->assertCount(1, $list);
        $this->assertInstanceOf(City::class, $list[0]);
        $this->assertSame('Berlin', $list[0]->getName());
    }
}

