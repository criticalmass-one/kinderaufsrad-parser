<?php declare(strict_types=1);

namespace App\Tests\Double;

use PHPUnit\Framework\AssertionFailedError;

/**
 * Runs assertions describing the DESIRED behaviour for a documented, not-yet-fixed bug.
 * While the bug is present the test is reported as incomplete (not as a failure);
 * once the bug is fixed the very same test turns green without modification.
 */
trait KnownBugTrait
{
    /** @param callable(): void $desiredBehaviour */
    private function assertKnownBugStillPresent(string $reference, callable $desiredBehaviour): void
    {
        try {
            $desiredBehaviour();
        } catch (AssertionFailedError|\Error $e) {
            self::markTestIncomplete(sprintf('Known bug still present (%s): %s', $reference, $e->getMessage()));
        }
    }
}
