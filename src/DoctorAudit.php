<?php

namespace Rushing\Doctor;

/**
 * The contract a package's readiness audit implements to be aggregated by a host doctor manifest — a
 * single argument-free `run()` returning {@see Finding}s. A registered audit resolves from the
 * container and runs with no per-check wiring in the doctor command.
 *
 * Governs the manifest-registered consumer tail. A host's own core audits may predate its manifest and
 * stay hardcoded in the command with bespoke `run(...)` signatures; this interface does not bind them.
 *
 * ## ⚠️ A `Pass` over an EMPTY population says "nothing here", not "measured clean"
 *
 * An audit's population is composed by the HOST — a registry it reads, a route table, a directory. Run
 * an audit outside the host that populates it and the population is empty, so the audit reports `Pass`
 * having measured nothing. Measured 2026-08-28 (api-surface-coherence 124): `UndeclaredInputAudit` run
 * from inside `laravel-beam-rank`'s own testbench sees `ParticleOperationRegistry->all()` → 0 ops and
 * reports `particle.operation-input: Pass` — negative-controlled, it still passes with the exact defect
 * it exists to catch deliberately introduced in that package's own source. Every registry-side audit
 * shares the shape.
 *
 * The status vocabulary CANNOT currently carry the distinction: {@see DoctorStatus} is Pass/Warn/Fail
 * with no inconclusive case, so `counts()`, `worst()` and the runner's floor all fold "empty" into
 * "clean". Whether it should gain one is an open ruling (api-surface-coherence 124) — deliberately not
 * settled here, because the severity ordinal it would take is the whole question.
 *
 * Until then, two obligations, and they are on the audit and its caller rather than on this contract:
 *
 *   1. **An audit NAMES its empty population in the finding's detail**, in prose, rather than falling
 *      through to a generic "clean" message. The `splicewire/laravel-beam` audits do this in eleven
 *      hand-written branches — "No particle operations are registered in this host." — which is why the
 *      distinction is legible to a human reading the report even though it is invisible to a machine
 *      reading the status.
 *   2. **A caller outside the intended host must not read a `Pass` as a measurement.** Nothing in this
 *      contract, in the runner, or in a doctor command REFUSES the out-of-host run — so a package
 *      testbench that wants a guard over its OWN source writes a package-local static test, not a
 *      borrowed host-side audit. See api-surface-coherence 121 for the three that shipped that way.
 */
interface DoctorAudit
{
    /**
     * @return list<Finding>
     */
    public function run(): array;
}
