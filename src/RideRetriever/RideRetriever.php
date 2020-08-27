<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class RideRetriever
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
        ]);
    }

    public function postRide(Ride $ride): self
    {
        $this->client->post(sprintf('/api/%s/%s', 'hamburg', 'kidical-mass'));

        return $this;
    }
}
