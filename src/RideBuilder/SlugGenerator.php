<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;
use Cocur\Slugify\Slugify;

class SlugGenerator implements SlugGeneratorInterface
{
    public function generateForRide(Ride $ride): Ride
    {
        if (!$ride->getCity() || !$ride->getCity()->getMainSlug() || !$ride->getDateTime()) {
            return $ride;
        }

        $slugify = new Slugify();

        $slugifiedCityName = $slugify->slugify($ride->getCityName());

        // copy() keeps the German locale off the ride's own Carbon instance.
        $monthName = $slugify->slugify($ride->getDateTime()->copy()->locale('de')->monthName);
        $year = $ride->getDateTime()->format('Y');

        $slug = sprintf('kidical-mass-%s-%s-%d', $slugifiedCityName, $monthName, $year);

        $ride->setSlug($slug);

        return $ride;
    }
}
