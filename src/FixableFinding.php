<?php

namespace Rushing\Doctor;

/**
 * A {@see Finding} that carries the operation which would CORRECT it, so a caller can generate the fix
 * rather than re-derive it from the detail string.
 *
 * The operation is deliberately typed `mixed` and never inspected here. This package is a moat-free
 * foundation primitive (ADR-0095) and must not learn the vocabulary of whichever tool suggests fixes — it
 * carries the suggestion opaquely. That opacity is also what makes the no-promotion guarantee structural
 * rather than a convention: a runner cannot promote a suggestion across an automated/guided/advisory tier
 * boundary it cannot read.
 */
class FixableFinding extends Finding
{
    public function __construct(
        DoctorStatus $status,
        string $check,
        string $detail,
        public mixed $operation = null,
    ) {
        parent::__construct($status, $check, $detail);
    }

    public static function warnFixable(string $check, string $detail, mixed $operation = null): self
    {
        return new self(DoctorStatus::Warn, $check, $detail, $operation);
    }

    public static function failFixable(string $check, string $detail, mixed $operation = null): self
    {
        return new self(DoctorStatus::Fail, $check, $detail, $operation);
    }
}
