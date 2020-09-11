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
        $dateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber'], ['', '', '03.', '09.'], $dateTimeSpec);

        $callableList = [
            function(string $dateTimeSpec, string $timezoneSpec) {
                return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);
            },
            function(string $dateTimeSpec, string $timezoneSpec) {
                $dateTimeSpec = str_replace(['x', 'X'], '', $dateTimeSpec);

                return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);
            },
            function(string $dateTimeSpec, string $timezoneSpec) {
                $dateTimeSpec = str_replace(' Uhr', ':00', $dateTimeSpec);

                return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);
            }
        ];

        foreach ($callableList as $callable) {
            try {
                $dateTime = $callable($dateTimeSpec, $timezoneSpec);

                if ($dateTime) {
                    return $dateTime;
                }
            } catch (\Exception $exception) {

            }
        }

        return null;
    }
}