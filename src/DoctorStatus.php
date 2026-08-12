<?php

namespace Rushing\Doctor;

/**
 * The Pass/Warn/Fail status vocabulary a readiness doctor reports each check in.
 * Moat-free foundation primitive (ADR-0095): both the free-tier Beam doctor and the
 * paid satellite doctor consume it from here, so a foundation package can self-diagnose
 * without requiring the product.
 */
enum DoctorStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';

    /**
     * The status's ordinal severity, so a runner can compare one against a configured FLOOR. The vocabulary
     * is ordered — a Warn is strictly worse than a Pass — but the enum's string backing carries no order, so
     * this is where the ordering is stated once rather than re-derived by every caller.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Pass => 0,
            self::Warn => 1,
            self::Fail => 2,
        };
    }

    /** Whether this status is at least as severe as `$floor` — the runner's throw test. */
    public function atLeast(self $floor): bool
    {
        return $this->severity() >= $floor->severity();
    }
}
