<?php declare(strict_types=1);

/*
 * Test-only override of file_get_contents() for the App\Command namespace.
 *
 * ParseCommand calls the unqualified `file_get_contents($url)`; PHP resolves that to
 * App\Command\file_get_contents() first, so registering a response here keeps the
 * command tests free of any network access. Unknown URLs fail loudly instead of
 * falling back to the real function.
 */

namespace App\Tests\Double {
    final class UMapResponses
    {
        /** @var array<string, string> */
        private static array $responses = [];

        /** @var list<string> */
        private static array $requestedUrls = [];

        public static function register(string $url, string $body): void
        {
            self::$responses[$url] = $body;
        }

        public static function reset(): void
        {
            self::$responses = [];
            self::$requestedUrls = [];
        }

        public static function fetch(string $url): string
        {
            self::$requestedUrls[] = $url;

            if (!array_key_exists($url, self::$responses)) {
                throw new \RuntimeException(sprintf('No mocked response registered for "%s" (network access is disabled in tests).', $url));
            }

            return self::$responses[$url];
        }

        /** @return list<string> */
        public static function requestedUrls(): array
        {
            return self::$requestedUrls;
        }
    }
}

namespace App\Command {
    use App\Tests\Double\UMapResponses;

    function file_get_contents(string $filename): string
    {
        return UMapResponses::fetch($filename);
    }
}
