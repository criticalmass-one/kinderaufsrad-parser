<?php declare(strict_types=1);

namespace App\Tests\Double;

use App\CityFetcher\CityFetcher;

/** Exposes the protected name mapping for tests. */
final class TestableCityFetcher extends CityFetcher
{
    public function exposeFixCityName(string $name): string
    {
        return $this->fixCityName($name);
    }
}
