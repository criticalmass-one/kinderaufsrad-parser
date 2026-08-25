<?php declare(strict_types=1);

namespace App\Tests\Double;

use App\Model\Ride;
use App\RidePusher\RidePusherInterface;

/** Records every push instead of talking to the criticalmass.in API. */
final class RecordingRidePusher implements RidePusherInterface
{
    /** @var list<Ride> */
    public array $putRides = [];

    /** @var list<Ride> */
    public array $postedRides = [];

    /** @var array<string, \Exception> slug => exception to throw on POST */
    private array $postFailures = [];

    public function failPostFor(string $slug, \Exception $exception): self
    {
        $this->postFailures[$slug] = $exception;

        return $this;
    }

    public function putRide(Ride $ride): RidePusherInterface
    {
        $this->putRides[] = $ride;

        return $this;
    }

    public function postRide(Ride $ride): RidePusherInterface
    {
        if (isset($this->postFailures[(string) $ride->getSlug()])) {
            throw $this->postFailures[(string) $ride->getSlug()];
        }

        $this->postedRides[] = $ride;

        return $this;
    }

    public function pushCount(): int
    {
        return count($this->putRides) + count($this->postedRides);
    }
}
