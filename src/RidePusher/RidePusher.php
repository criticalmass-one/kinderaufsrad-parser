<?php declare(strict_types=1);

namespace App\RidePusher;

use App\Model\Ride;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class RidePusher
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        $this->client = new Client();
    }

    public function postRide(Ride $ride): self
    {
        $this->client->post('https://criticalmass.wip/api/');

        return $this;
    }
}