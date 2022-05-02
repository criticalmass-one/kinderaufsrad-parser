<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;

interface RideBuilderInterface
{
    public function buildFromFeature(\stdClass $feature): ?Ride;
}
