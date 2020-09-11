<?php declare(strict_types=1);

namespace App\RideBuilder;

use Carbon\Carbon;

class DateTimeDetector
{
    private function __construct()
    {

    }

    public static function detect(string $dateTimeSpec, string $timezoneSpec): ?Carbon
    {
        $dateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber'], ['', '', '03.', '09.'], $dateTimeSpec);

        try {
            return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);

        } catch (\Exception $exception) {
            try {
                $dateTimeSpec = str_replace(['x', 'X'], '', $dateTimeSpec);
                return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);
            } catch (\Exception $exception) {
                try {
                    $dateTimeSpec = str_replace(' Uhr', ':00', $dateTimeSpec);
                    return Carbon::parseFromLocale($dateTimeSpec, null, $timezoneSpec);
                } catch (\Exception $exception) {

                }
            }
        }
    }
}