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

        $slugifiedCityName = (new Slugify())->slugify($ride->getCityName());

        $monthName = $ride->getDateTime()->locale('de')->monthName;
        $year = $ride->getDateTime()->format('Y');

        $slug = sprintf('kidical-mass-%s-%s-%d', $slugifiedCityName, $monthName, $year);

        $slug = strtolower($slug);

        $ride->setSlug($slug);

        return $ride;
    }
}
