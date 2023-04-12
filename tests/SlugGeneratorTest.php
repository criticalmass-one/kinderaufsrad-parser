<?php declare(strict_types=1);

namespace App\Tests;

use App\Model\City;
use App\Model\CitySlug;
use App\Model\Ride;
use App\RideBuilder\SlugGenerator;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class SlugGeneratorTest extends TestCase
{
    public function testGenerateForRideWithValidData()
    {
        // Arrange
        $city = new City();
        $city->setMainSlug((new CitySlug())->setSlug('berlin'));

        $dateTime = Carbon::parse('2023-04-12');

        $ride = new Ride();
        $ride->setCity($city);
        $ride->setDateTime($dateTime);

        $slugGenerator = new SlugGenerator();

        // Act
        $result = $slugGenerator->generateForRide($ride);

        // Assert
        $this->assertEquals('kidical-mass-berlin-april-2023', $result->getSlug());
    }

    public function testGenerateForRideWithMissingCity()
    {
        // Arrange
        $ride = new Ride();
        $slugGenerator = new SlugGenerator();

        // Act
        $result = $slugGenerator->generateForRide($ride);

        // Assert
        $this->assertEquals(null, $result->getSlug());
    }

    public function testGenerateForRideWithMissingMainSlug()
    {
        // Arrange
        $city = new City();

        $ride = new Ride();
        $ride->setCity($city);

        $slugGenerator = new SlugGenerator();

        // Act
        $result = $slugGenerator->generateForRide($ride);

        // Assert
        $this->assertEquals(null, $result->getSlug());
    }

    public function testGenerateForRideWithMissingDateTime()
    {
        // Arrange
        $city = new City();
        $city->setMainSlug((new CitySlug())->setSlug('berlin'));

        $ride = new Ride();
        $ride->setCity($city);

        $slugGenerator = new SlugGenerator();

        // Act
        $result = $slugGenerator->generateForRide($ride);

        // Assert
        $this->assertEquals(null, $result->getSlug());
    }
}