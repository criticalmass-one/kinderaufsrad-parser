<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use JMS\Serializer\SerializerInterface;

class RideRetriever implements RideRetrieverInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
            'verify' => false,
        ]);
    }

    public function doesRideExist(Ride $ride): bool
    {
        if (!$ride->getCity()) {
            return false;
        }

        $citySlug = $ride->getCity()->getMainSlug()->getSlug();
        $rideSlug = $ride->getSlug();

        try {
            $this->client->get(sprintf('/api/%s/%s', $citySlug, $rideSlug));
        } catch (ClientException $clientException) {
            return false;
        }

        return true;
    }

    public function fetchOriginalRide(Ride $ride): ?Ride
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();
        $rideSlug = $ride->getSlug();

        try {
            $response = $this->client->get(sprintf('/api/%s/%s', $citySlug, $rideSlug));
        } catch (ClientException $clientException) {
            return null;
        }

        $ride = $this->serializer->deserialize($response->getBody()->getContents(), Ride::class, 'json');

        return $ride;
    }
}
