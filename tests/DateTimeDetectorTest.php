<?php declare(strict_types=1);

namespace App\Tests;

use App\RideBuilder\DateTimeDetector;
use PHPUnit\Framework\TestCase;

class DateTimeDetectorTest extends TestCase
{
    /**
     * @dataProvider dateTimeDataProvider
     */
    public function testDateTimeDetector(string $dateTimeSpec, string $expectedDateTime): void
    {
        $dateTime = DateTimeDetector::detect($dateTimeSpec, 'Europe/Berlin');

        $this->assertNotNull($dateTime);

        $this->assertEquals($expectedDateTime, $dateTime->format('Y-m-d H:i'));
    }

    public function dateTimeDataProvider(): array
    {
        return [
            ['19. September 2020, 11.00 Uhr', '2020-09-19 11:00'],
            ['20. September 2020, 15.00 Uhr', '2020-09-20 15:00'],
            ['20. September 2020, 15 Uhr', '2020-09-20 15:00'],
            ['*26. September 2020, 14.00 Uhr', '2020-09-26 14:00'],
            ['*26. September 2020, 14.00 Uhr  ', '2020-09-26 14:00'],
        ];
    }
}