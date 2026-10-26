<?php declare(strict_types=1);

namespace App\RideRetriever;

use App\Model\Ride;

interface RideRetrieverInterface
{
    public function doesRideExist(Ride $ride): bool;
    public function fetchOriginalRide(Ride $ride): ?Ride;
}
