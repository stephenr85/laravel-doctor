<?php

namespace Rushing\Doctor;

/**
 * One consumer package's audit registration in a host doctor manifest. Carries the audit class to
 * resolve and run, an ordering hint (core-first), and the gate/advisory flag: a `gate` audit's
 * {@see DoctorStatus::Fail} turns the doctor command red, an advisory one renders Pass/Warn/Fail but
 * never fails the exit code.
 */
class DoctorRegistration
{
    /**
     * @param  string  $package  the registering package's name (operator output only — the host never branches on it)
     * @param  class-string<DoctorAudit>  $audit  the audit to resolve from the container and run
     * @param  bool  $gate  whether a Fail from this audit blocks the exit code (default advisory)
     * @param  int  $order  lower runs first; core audits render first, consumers default to 100
     */
    public function __construct(
        public string $package,
        public string $audit,
        public bool $gate = false,
        public int $order = 100,
    ) {}
}
