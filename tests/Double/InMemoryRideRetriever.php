<?php declare(strict_types=1);

namespace App\Tests\Double;

use App\Model\Ride;
use App\RideRetriever\RideRetrieverInterface;

/** Answers doesRideExist() from a fixed set of slugs. */
final class InMemoryRideRetriever implements RideRetrieverInterface
{
    /** @var list<string> */
    private array $existingSlugs = [];

    /** @var list<string|null> */
    public array $checkedSlugs = [];

    public function markExisting(string ...$slugs): self
    {
        $this->existingSlugs = array_merge($this->existingSlugs, $slugs);

        return $this;
    }

    public function doesRideExist(Ride $ride): bool
    {
        $this->checkedSlugs[] = $ride->getSlug();

        return in_array($ride->getSlug(), $this->existingSlugs, true);
    }

    public function fetchOriginalRide(Ride $ride): ?Ride
    {
        return $this->doesRideExist($ride) ? $ride : null;
    }
}
