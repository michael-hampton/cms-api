<?php

namespace App\Services\OpenCollab;

use App\Exceptions\OpenCollab\ContributorUnderageException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * ContributorAgeValidationService
 *
 * Encapsulates all age-based eligibility logic for contributors.
 *
 * Design decisions:
 *   - All calculations use UTC to avoid timezone boundary issues.
 *   - Leap-year birthdays (Feb 29) are handled: the birthday is
 *     considered to fall on Mar 1 in non-leap years.
 *   - This service is a pure calculation collaborator — no persistence.
 *     Callers are responsible for providing valid DOBs and storing results.
 */
class ContributorAgeValidationService
{
    /**
     * Calculates the contributor's age in completed years as of today (UTC).
     *
     * @throws \InvalidArgumentException if $dob is in the future.
     */
    public function calculateAge(DateTimeImmutable $dob): int
    {
        $today = $this->today();

        if ($dob > $today) {
            throw new \InvalidArgumentException('Date of birth cannot be in the future.');
        }

        $age = (int)$today->diff($dob)->y;

        return $age;
    }

    /**
     * Returns true if the contributor meets or exceeds the minimum age.
     */
    public function meetsMinimumAge(DateTimeImmutable $dob, int $minimumAge): bool
    {
        return $this->calculateAge($dob) >= $minimumAge;
    }

    /**
     * Throws ContributorUnderageException if the contributor is below minimum age.
     *
     * @throws ContributorUnderageException
     * @throws \InvalidArgumentException
     */
    public function assertEligible(DateTimeImmutable $dob, int $minimumAge): void
    {
        $age = $this->calculateAge($dob);

        if ($age < $minimumAge) {
            throw new ContributorUnderageException(
                contributorAge: $age,
                minimumAge: $minimumAge,
            );
        }
    }

    /**
     * Parses a date string (Y-m-d) into a UTC DateTimeImmutable.
     * Returns null if the string is invalid or empty.
     */
    public function parseDob(?string $dobString): ?DateTimeImmutable
    {
        if (empty($dobString)) {
            return null;
        }

        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $dobString, new DateTimeZone('UTC'));

        if (!$dob || $dob->format('Y-m-d') !== $dobString) {
            return null; // Malformed input — e.g. "2000-13-01"
        }

        return $dob;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('UTC'));
    }
}