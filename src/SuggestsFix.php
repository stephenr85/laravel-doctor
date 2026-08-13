<?php

namespace Rushing\Doctor;

/**
 * A {@see Finding} that can hand over the operation which would CORRECT it, so a caller acting on a failure
 * can generate the fix rather than re-derive it from the detail string.
 *
 * An INTERFACE rather than a Finding subclass, and the suggestion is `mixed`, for two reasons:
 *
 *   1. `Rushing\Surgeon\Operation\FixableFinding` already models "diagnosis beside deterministic fix" one
 *      layer up (surgeon depends on this package). A second value object here would be a duplicate concept
 *      under a colliding name, in two packages that get imported together. A marker interface lets whichever
 *      layer owns the fix vocabulary keep owning it, and lets its findings opt in.
 *   2. This is a moat-free foundation primitive (ADR-0095), so it must not learn the vocabulary of whichever
 *      tool suggests fixes. That opacity is also what makes {@see DoctorRunner}'s no-promotion guarantee
 *      structural rather than a convention: the runner cannot promote a suggestion across an
 *      automated/guided/advisory tier boundary it is unable to read.
 *
 * INTENTIONALLY UNUSED SEAM (verified 2026-08-13): no Finding in the estate implements this yet, so
 * {@see DoctorReport::fixable()} and {@see DoctorFailed::fixable()} currently return empty lists. That is a
 * decision, not an oversight — surgeon's `FixableFinding` wraps a Finding by composition rather than
 * subclassing it, so today's only fix vocabulary cannot flow through here, and a contrived implementor just
 * to exercise the methods would be dishonest. The seam stays because it is the declared opt-in path for the
 * first Finding subclass that genuinely carries its own correction.
 */
interface SuggestsFix
{
    /** The suggested correction, or null when this finding has no deterministic fix. */
    public function fixSuggestion(): mixed;
}
