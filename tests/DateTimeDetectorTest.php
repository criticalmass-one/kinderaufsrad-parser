<?php declare(strict_types=1);

namespace App\Tests;

use App\RideBuilder\DateTimeDetector;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DateTimeDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon('2026-04-01 12:00:00', 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    #[DataProvider('dateTimeDataProvider')]
    public function testDateTimeDetector(string $dateTimeSpec, string $expectedDateTime): void
    {
        $dateTime = DateTimeDetector::detect($dateTimeSpec, 'Europe/Berlin');

        $this->assertNotNull($dateTime);

        $this->assertEquals($expectedDateTime, $dateTime->format('Y-m-d H:i'));
    }

    public static function dateTimeDataProvider(): array
    {
        return [
            ['19. September 2020, 11.00 Uhr', '2020-09-19 11:00'],
            ['20. September 2020, 15.00 Uhr', '2020-09-20 15:00'],
            ['20. September 2020, 15 Uhr', '2020-09-20 15:00'],
            ['20. September 2020, 15:00 Uhr', '2020-09-20 15:00'],
            ['26. September 2020, 14.00 Uhr  ', '2020-09-26 14:00'],
            ['20.09.2020 15:00', '2020-09-20 15:00'],
            ['2020-09-20 15:00', '2020-09-20 15:00'],
        ];
    }

    /**
     * Leading "*" / "x" markers flag entries in the source list and must be ignored.
     */
    #[DataProvider('leadingMarkerDataProvider')]
    public function testLeadingMarkersAreIgnored(string $dateTimeSpec, string $expectedDateTime): void
    {
        $dateTime = DateTimeDetector::detect($dateTimeSpec, 'Europe/Berlin');

        $this->assertNotNull($dateTime);
        $this->assertEquals($expectedDateTime, $dateTime->format('Y-m-d H:i'));
        $this->assertSame('Europe/Berlin', $dateTime->getTimezone()->getName());
    }

    public static function leadingMarkerDataProvider(): array
    {
        return [
            ['*26. September 2020, 14.00 Uhr', '2020-09-26 14:00'],
            ['*26. September 2020, 14.00 Uhr  ', '2020-09-26 14:00'],
            ['* 26. September 2020, 14.00 Uhr', '2020-09-26 14:00'],
            ['x 20. September 2020, 15 Uhr', '2020-09-20 15:00'],
            ['X20. September 2020, 15 Uhr', '2020-09-20 15:00'],
        ];
    }

    #[Test]
    public function timezoneSpecIsApplied(): void
    {
        $berlin = DateTimeDetector::detect('19. September 2020, 11.00 Uhr', 'Europe/Berlin');
        $utc = DateTimeDetector::detect('19. September 2020, 11.00 Uhr', 'UTC');

        $this->assertSame('Europe/Berlin', $berlin?->getTimezone()->getName());
        $this->assertSame('UTC', $utc?->getTimezone()->getName());
        $this->assertSame(7200, $utc?->timestamp - $berlin?->timestamp, 'same wall clock time, two hours apart in CEST vs UTC');
    }

    #[Test]
    public function returnsNullForGarbage(): void
    {
        $this->assertNull(DateTimeDetector::detect('garbage', 'Europe/Berlin'));
    }

    #[Test]
    public function emptySpecYieldsNull(): void
    {
        $this->assertNull(DateTimeDetector::detect('', 'Europe/Berlin'));
        $this->assertNull(DateTimeDetector::detect('   ', 'Europe/Berlin'));
        $this->assertNull(DateTimeDetector::detect('*', 'Europe/Berlin'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function germanSpecProvider(): iterable
    {
        yield 'German March' => ['19. März 2021, 11 Uhr', '2021-03-19 11:00'];
        yield 'German March with HH.MM' => ['19. März 2021, 11.30 Uhr', '2021-03-19 11:30'];
        yield 'Septmber typo' => ['3. Septmber 2022, 14.00 Uhr', '2022-09-03 14:00'];
        yield 'leading weekday' => ['Samstag, 19. September 2020, 11 Uhr', '2020-09-19 11:00'];
        yield 'leading weekday without comma' => ['Sonntag 1. Mai 2022 10 Uhr', '2022-05-01 10:00'];
        yield 'other German months' => ['24. Dezember 2021, 16 Uhr', '2021-12-24 16:00'];
        yield 'lower-case month' => ['5. juni 2022, 14 Uhr', '2022-06-05 14:00'];
    }

    #[Test]
    #[DataProvider('germanSpecProvider')]
    public function germanMonthAndWeekdayNamesAreUnderstood(string $spec, string $expected): void
    {
        $this->assertSame($expected, DateTimeDetector::detect($spec, 'Europe/Berlin')?->format('Y-m-d H:i'));
    }
}
