<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Serializer\SerializerInterface;

class RideRetriever implements RideRetrieverInterface
{
    protected Client $client;

    public function __construct(protected SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
            'verify' => false,
        ]);
    }

    public function doesRideExist(Ride $ride): bool
    {
        if (!$ride->getCity() || !$ride->getSlug()) {
            return false;
        }

        $citySlug = $ride->getCity()->getMainSlug()->getSlug();
        $rideSlug = $ride->getSlug();

        $existingRide = $this->fetchBySlugs($citySlug, $rideSlug);

        if (!$existingRide && $ride->getDateTime()) {
            $rideSlug = $ride->getDateTime()->format('Y-m-d');

            $existingRide = $this->fetchBySlugs($citySlug, $rideSlug);
        }

        return $existingRide !== null;
    }

    public function fetchOriginalRide(Ride $ride): ?Ride
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();
        $rideSlug = $ride->getSlug();

        return $this->fetchBySlugs($citySlug, $rideSlug);
    }

    public function fetchBySlugs(string $citySlug, string $rideIdentifier): ?Ride
    {
        try {
            $response = $this->client->get(sprintf('/api/%s/%s', $citySlug, $rideIdentifier));
        } catch (ClientException) {
            return null;
        }

        $ride = $this->serializer->deserialize($response->getBody()->getContents(), Ride::class, 'json');

        return $ride;
    }
}
