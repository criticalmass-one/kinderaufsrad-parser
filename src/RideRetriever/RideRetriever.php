<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;
use App\Serializer\Denormalizer\CarbonDenormalizer;
use App\Serializer\Denormalizer\CityDenormalizer;
use App\Serializer\Denormalizer\RideDenormalizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class RideRetriever implements RideRetrieverInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(string $criticalmassHostname)
    {
        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
            'verify' => false,
        ]);

        $normalizers = [
            new RideDenormalizer(new CityDenormalizer()),
            new JsonSerializableNormalizer(),
            new ObjectNormalizer(),
            new ArrayDenormalizer(),
        ];

        $encoders = [new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
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

        $rawContent = $response->getBody()->getContents();

        $ride = $this->serializer->deserialize($rawContent, Ride::class, 'json');

        return $ride;
    }
}
