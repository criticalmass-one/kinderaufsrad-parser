<?php declare(strict_types=1);

namespace App\RidePusher;

use App\Model\Ride;
use GuzzleHttp\Client;
use Symfony\Component\Serializer\SerializerInterface;

class RidePusher implements RidePusherInterface
{
    protected Client $client;

    public function __construct(protected SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
            'verify' => false,
        ]);
    }

    public function postRide(Ride $ride): self
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();

        $apiUrl = sprintf('/api/%s/%s', $citySlug, $ride->getSlug());

        $this->client->post($apiUrl, [
            'body' => $this->serializer->serialize($ride, 'json'),
        ]);

        return $this;
    }

    public function putRide(Ride $ride): self
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();

        $apiUrl = sprintf('/api/%s/%s', $citySlug, $ride->getSlug());

        $this->client->put($apiUrl, [
            'body' => $this->serializer->serialize($ride, 'json'),
        ]);

        return $this;
    }
}
