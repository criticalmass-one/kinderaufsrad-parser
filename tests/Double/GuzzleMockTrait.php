<?php declare(strict_types=1);

namespace App\Tests\Double;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

/**
 * Builds a Guzzle client backed by a MockHandler and injects it into services
 * that construct their own Client (CityFetcher, RidePusher, RideRetriever).
 */
trait GuzzleMockTrait
{
    /** @var list<array{request: RequestInterface, response: mixed}> */
    private array $httpHistory = [];

    private MockHandler $mockHandler;

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $queue */
    private function createMockedClient(array $queue, string $baseUri = 'https://cm.test/'): Client
    {
        $this->httpHistory = [];
        $this->mockHandler = new MockHandler($queue);

        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(Middleware::history($this->httpHistory));

        return new Client([
            'handler' => $stack,
            'base_uri' => $baseUri,
        ]);
    }

    private function injectClient(object $service, Client $client): void
    {
        $property = new \ReflectionProperty($service, 'client');
        $property->setValue($service, $client);
    }

    /** @return list<RequestInterface> */
    private function sentRequests(): array
    {
        return array_map(static fn(array $entry): RequestInterface => $entry['request'], $this->httpHistory);
    }

    private function lastRequest(): RequestInterface
    {
        $requests = $this->sentRequests();

        self::assertNotEmpty($requests, 'Expected at least one HTTP request to have been sent.');

        return end($requests);
    }

    /** @return array<string, string> */
    private function queryOf(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        return $query;
    }
}
