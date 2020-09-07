<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use JMS\Serializer\SerializerInterface;

interface RideRetrieverInterface
{
    public function doesRideExist(Ride $ride): bool;
    public function fetchOriginalRide(Ride $ride): ?Ride;
}
