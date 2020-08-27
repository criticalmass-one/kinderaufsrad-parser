<?php declare(strict_types=1);

namespace App\CityFetcher;

use App\Model\City;
use App\Model\Ride;

interface CityFetcherInterface
{
    public function getCityForRide(Ride $ride): ?City;
    public function getCityForName(string $name): ?City;
}