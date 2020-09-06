<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;

interface SlugGeneratorInterface
{
    public function generateForRide(Ride $ride): Ride;
}
