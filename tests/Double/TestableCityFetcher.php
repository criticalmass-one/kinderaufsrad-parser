<?php declare(strict_types=1);

namespace App\Tests\Double;

use App\CityFetcher\CityFetcher;
use Symfony\Component\Serializer\Serializer;

/**
 * Declares the $serializer property CityFetcher assigns dynamically (which PHP >= 8.2
 * reports as deprecated) and exposes the protected name mapping for tests.
 */
final class TestableCityFetcher extends CityFetcher
{
    protected Serializer $serializer;

    public function exposeFixCityName(string $name): string
    {
        return $this->fixCityName($name);
    }
}
