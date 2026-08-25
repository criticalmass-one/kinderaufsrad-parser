<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;
use Cocur\Slugify\Slugify;

/**
 * Slug = kidical-mass-{slugified OSM name}-{deutscher Monat}-{Jahr}.
 *
 * Several rides of one OSM name in the same month would collide, so the slug is made unique
 * per generator instance (one instance lives for one command run):
 *  1. "Strecke N" in the description (Wien publishes one feature per route) -> "-strecke-N";
 *  2. otherwise, if the slug was already issued to another ride, "-HHMM" (start time);
 *  3. if that is taken as well, a counter "-2", "-3", ...
 */
class SlugGenerator implements SlugGeneratorInterface
{
    /** @var array<string, \WeakReference<Ride>> slug => ride it was issued to */
    private array $issuedSlugs = [];

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

        $routeNumber = $this->extractRouteNumber($ride->getDescription());

        if ($routeNumber !== null) {
            $slug .= '-strecke-' . $routeNumber;
        }

        $slug = $this->makeUnique($slug, $ride);

        $ride->setSlug($slug);
        $this->issuedSlugs[$slug] = \WeakReference::create($ride);

        return $ride;
    }

    protected function extractRouteNumber(?string $description): ?int
    {
        if ($description !== null && preg_match('/\bStrecke\s*(\d+)/iu', $description, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function makeUnique(string $slug, Ride $ride): string
    {
        if ($this->isFree($slug, $ride)) {
            return $slug;
        }

        $slug .= '-' . $ride->getDateTime()->format('Hi');

        if ($this->isFree($slug, $ride)) {
            return $slug;
        }

        for ($counter = 2; ; ++$counter) {
            $candidate = sprintf('%s-%d', $slug, $counter);

            if ($this->isFree($candidate, $ride)) {
                return $candidate;
            }
        }
    }

    private function isFree(string $slug, Ride $ride): bool
    {
        $owner = isset($this->issuedSlugs[$slug]) ? $this->issuedSlugs[$slug]->get() : null;

        return $owner === null || $owner === $ride;
    }
}
