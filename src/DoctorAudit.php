<?php

namespace Rushing\Doctor;

/**
 * The contract a package's readiness audit implements to be aggregated by a host doctor manifest — a
 * single argument-free `run()` returning {@see Finding}s. A registered audit resolves from the
 * container and runs with no per-check wiring in the doctor command.
 *
 * Governs the manifest-registered consumer tail. A host's own core audits may predate its manifest and
 * stay hardcoded in the command with bespoke `run(...)` signatures; this interface does not bind them.
 */
interface DoctorAudit
{
    /**
     * @return list<Finding>
     */
    public function run(): array;
}
