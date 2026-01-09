<?php declare(strict_types=1);

namespace App\Tests;

use App\CityFetcher\CachedCityFetcher;
use App\Model\City;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CachedCityFetcherTest extends TestCase
{
    private function buildCachedFetcherWithMockedHttp(array $responses, array &$historyContainer): CachedCityFetcher
    {
        $fetcher = new CachedCityFetcher('https://example.invalid');

        // Cache pro Testlauf isolieren, damit nicht versehentlich ein alter Cache-Hit entsteht.
        $uniqueNamespace = 'kidicalmass-city-test-' . bin2hex(random_bytes(8));
        $cache = new FilesystemAdapter($uniqueNamespace, CachedCityFetcher::CACHE_TTL);

        $refFetcher = new \ReflectionClass($fetcher);
        $cacheProp = $refFetcher->getProperty('cache');
        $cacheProp->setValue($fetcher, $cache);

        $mock = new MockHandler($responses);
        $history = Middleware::history($historyContainer);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $client = new Client([
            'base_uri' => 'https://example.invalid',
            'handler' => $handlerStack,
        ]);

        $prop = $refFetcher->getParentClass()->getProperty('client');
        $prop->setValue($fetcher, $client);

        return $fetcher;
    }

    public function testGetCityForNameCachesResultAndAvoidsSecondHttpCall(): void
    {
        $payload = json_encode([
            [
                'id' => 2,
                'name' => 'Berlin',
                'timezone' => 'Europe/Berlin',
                'latitude' => 52.52,
                'longitude' => 13.405,
                'main_slug' => ['id' => 1, 'slug' => 'berlin'],
            ],
        ], JSON_THROW_ON_ERROR);

        $history = [];
        $fetcher = $this->buildCachedFetcherWithMockedHttp([
            new Response(200, ['Content-Type' => 'application/json'], $payload),
        ], $history);

        $first = $fetcher->getCityForName('Berlin');
        $second = $fetcher->getCityForName('Berlin');

        $this->assertInstanceOf(City::class, $first);
        $this->assertInstanceOf(City::class, $second);
        $this->assertSame('Berlin', $second->getName());

        // Der zweite Aufruf muss aus dem Cache kommen -> es gibt genau einen HTTP-Request.
        $this->assertCount(1, $history);
    }
}
