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
        $this->assertEquals($expectedDateTime, (DateTimeDetector::detect($dateTimeSpec, 'Europe/Berlin'))->format('Y-m-d H:i'));
    }

    public function dateTimeDataProvider(): array
    {
        return [
            ['19. September 2020, 11.00 Uhr', '2020-09-19 11:00'],
        ];
    }
}