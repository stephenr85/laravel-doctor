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
 * **Say so with {@see Finding::inconclusive()}.** The distinction is NOT on {@see DoctorStatus}: the enum
 * stays Pass/Warn/Fail and an inconclusive finding still reports `Pass`, so `counts()`, `worst()` and every
 * `--floor` behave exactly as before. Conclusiveness is a flag on the {@see Finding} instead — ruled
 * 2026-08-30 (api-surface-coherence 124), off the enum because "inconclusive" has no honest ordinal on a
 * linear severity ladder, and following the gate/advisory precedent {@see DoctorRegistration} already set
 * for an orthogonal axis that must not be folded into severity. Read the population back with
 * {@see DoctorReport::inconclusive()}.
 *
 * Three obligations, and the first two are on the audit and its caller rather than on this contract:
 *
 *   1. **An audit NAMES its empty population in the finding's detail**, in prose, rather than falling
 *      through to a generic "clean" message. The `splicewire/laravel-beam` audits do this in eleven
 *      hand-written branches — "No particle operations are registered in this host." — which is what made
 *      the distinction legible to a human even while it was invisible to a machine. The flag does not
 *      replace the prose; it makes the same statement readable by both.
 *   2. **A caller outside the intended host must not read a `Pass` as a measurement.** Nothing in this
 *      contract, in the runner, or in a doctor command REFUSES the out-of-host run — so a package
 *      testbench that wants a guard over its OWN source writes a package-local static test, not a
 *      borrowed host-side audit. See api-surface-coherence 121 for the three that shipped that way.
 *   3. **Nothing gates on the flag, and that is deliberate — for now.** 124's ruling ships it as a first
 *      move with a stated review condition: once a meaningful population of audits emits inconclusive
 *      findings, re-examine whether a floor should see them, with the call-site evidence the flag was
 *      chosen to generate. The named risk it is guarding against is a flag nobody reads becoming one more
 *      prose branch wearing a type.
 */
interface DoctorAudit
{
    /**
     * @return list<Finding>
     */
    public function run(): array;
}
