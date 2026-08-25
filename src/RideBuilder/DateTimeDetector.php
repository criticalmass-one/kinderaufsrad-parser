<?php declare(strict_types=1);

namespace App\RideBuilder;

use Carbon\Carbon;

/**
 * Parses free-text German date specs such as "*26. September 2020, 14.00 Uhr",
 * "Samstag, 19. März 2021, 11 Uhr", "20.09.2020 15:00" or "2020-09-20 15:00".
 */
class DateTimeDetector
{
    private const array WEEKDAYS = [
        'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonnabend', 'Sonntag',
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    private const array MONTHS = [
        'Januar' => 'January',
        'Februar' => 'February',
        'März' => 'March',
        'Maerz' => 'March',
        'Mai' => 'May',
        'Juni' => 'June',
        'Juli' => 'July',
        'Oktober' => 'October',
        'Dezember' => 'December',
        'Septmber' => 'September',
    ];

    private function __construct()
    {

    }

    public static function detect(string $dateTimeSpec, string $timezoneSpec): ?Carbon
    {
        $dateTimeSpec = self::normalize($dateTimeSpec);

        if ($dateTimeSpec === '') {
            return null;
        }

        try {
            return Carbon::parse($dateTimeSpec, $timezoneSpec);
        } catch (\Exception) {
            return null;
        }
    }

    private static function normalize(string $dateTimeSpec): string
    {
        // Leading markers ("*", "x") flag entries in the source list and carry no date information.
        // They must go before parsing: Carbon would otherwise read "x" as the military timezone X.
        $dateTimeSpec = (string) preg_replace('/^[\s*xX]+/u', '', $dateTimeSpec);

        $dateTimeSpec = str_replace(',', ' ', $dateTimeSpec);

        $dateTimeSpec = (string) preg_replace('/^(?:' . implode('|', self::WEEKDAYS) . ')\b\.?\s*/iu', '', $dateTimeSpec);

        $dateTimeSpec = (string) preg_replace_callback(
            '/\b(' . implode('|', array_map(preg_quote(...), array_keys(self::MONTHS))) . ')\b/iu',
            static fn(array $matches): string => self::MONTHS[ucfirst(mb_strtolower($matches[1]))] ?? $matches[1],
            $dateTimeSpec,
        );

        // "14.00 Uhr" / "14:00 Uhr" -> "14:00", "15 Uhr" -> "15:00"
        $dateTimeSpec = (string) preg_replace('/\b(\d{1,2})[.:](\d{2})\s*Uhr\b/iu', '$1:$2', $dateTimeSpec);
        $dateTimeSpec = (string) preg_replace('/\b(\d{1,2})\s*Uhr\b/iu', '$1:00', $dateTimeSpec);

        return trim((string) preg_replace('/\s+/u', ' ', $dateTimeSpec));
    }
}
