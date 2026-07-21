<?php

namespace Rushing\Doctor;

/**
 * One readiness-check result: a {@see DoctorStatus}, the check's name, and a human detail.
 * The unit every doctor audit emits. Moat-free foundation primitive (ADR-0095).
 */
class Finding
{
    public function __construct(
        public DoctorStatus $status,
        public string $check,
        public string $detail,
    ) {}

    public static function pass(string $check, string $detail): self
    {
        return new self(DoctorStatus::Pass, $check, $detail);
    }

    public static function warn(string $check, string $detail): self
    {
        return new self(DoctorStatus::Warn, $check, $detail);
    }

    public static function fail(string $check, string $detail): self
    {
        return new self(DoctorStatus::Fail, $check, $detail);
    }
}
