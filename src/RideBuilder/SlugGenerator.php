<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;

class SlugGenerator implements SlugGeneratorInterface
{
    public function generateForRide(Ride $ride): Ride
    {
        if (!$ride->getCity() || !$ride->getCity()->getMainSlug() || !$ride->getDateTime()) {
            return $ride;
        }

        $citySlug = $ride->getCity()->getMainSlug()->getSlug();
        $monthName = $ride->getDateTime()->locale('de')->monthName;
        $year = $ride->getDateTime()->format('Y');

        $slug = sprintf('kidical-mass-%s-%s-%d', $citySlug, $monthName, $year);

        $slug = strtolower($slug);

        $ride->setSlug($slug);

        return $ride;
    }
}
