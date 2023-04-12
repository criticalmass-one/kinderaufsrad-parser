<?php declare(strict_types=1);

namespace App\RideBuilder;

use Carbon\Carbon;
use function foo\func;

class DateTimeDetector
{
    private function __construct()
    {

    }

    public static function detect(string $dateTimeSpec, string $timezoneSpec): ?Carbon
    {
        $callableList = [
            function(string $dateTimeSpec, string $timezoneSpec) {
                $dateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber'], ['', '', '03.', '09.'], $dateTimeSpec);

                return Carbon::parseFromLocale(trim($dateTimeSpec), null, $timezoneSpec);
            },
            function(string $dateTimeSpec, string $timezoneSpec) {
                $dateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber', 'x', 'X'], ['', '', '03.', '09.', '', ''], $dateTimeSpec);

                return Carbon::parseFromLocale(trim($dateTimeSpec), null, $timezoneSpec);
            },
            function(string $dateTimeSpec, string $timezoneSpec) {
                $dateTimeSpec = str_replace(' Uhr', ':00', $dateTimeSpec);

                return Carbon::parseFromLocale(trim($dateTimeSpec), null, $timezoneSpec);
            }
        ];

        foreach ($callableList as $callable) {
            try {
                $dateTime = $callable($dateTimeSpec, $timezoneSpec);

                if ($dateTime) {
                    return $dateTime;
                }
            } catch (\Exception) {

            }
        }

        return null;
    }
}