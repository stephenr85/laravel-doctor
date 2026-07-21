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
}
