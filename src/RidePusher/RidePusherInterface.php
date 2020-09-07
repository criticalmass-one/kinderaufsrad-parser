<?php declare(strict_types=1);

namespace App\RidePusher;

use App\Model\Ride;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

interface RidePusherInterface
{
    public function putRide(Ride $ride): self;
    public function postRide(Ride $ride): self;
}
