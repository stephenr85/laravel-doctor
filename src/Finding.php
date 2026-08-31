<?php

namespace Rushing\Doctor;

/**
 * One readiness-check result: a {@see DoctorStatus}, the check's name, and a human detail.
 * The unit every doctor audit emits. Moat-free foundation primitive (ADR-0095).
 *
 * ## Conclusiveness is a FLAG here, not a fourth status
 *
 * `$conclusive` says whether the check actually measured its subject. It is deliberately OFF the
 * {@see DoctorStatus} enum, ruled 2026-08-30 (api-surface-coherence 124) after the alternative — a
 * fourth `Skip` case — was costed and rejected. Two reasons, both load-bearing:
 *
 *   1. **The enum is a linear ladder and "inconclusive" is not a point on it.** `severity()` orders
 *      Pass < Warn < Fail so a runner can compare against a floor. A status that measured nothing has
 *      no honest ordinal: below Pass it is unobservable, at or above Warn it reddens every package
 *      testbench and every partially-composed host for audits whose population it legitimately does
 *      not have.
 *   2. **The estate already solved this shape once, off the enum.** {@see DoctorRegistration}'s
 *      gate/advisory flag is likewise orthogonal to the floor and still governs. Conclusiveness is
 *      orthogonal to severity in exactly the same way.
 *
 * Consequences, which are the point rather than a limitation: an inconclusive finding carries a real
 * status (`Pass`, normally), so `worst()`, `counts()`, `atLeast()` and every `--floor` see precisely
 * what they saw before. **Nothing gates on it, and no exit code changes.** What it buys is that the
 * distinction stops living only in hand-written prose — {@see DoctorReport::inconclusive()} can be
 * read by a machine, and a renderer can stop printing `[PASS]` over a measurement that never happened.
 *
 * ⚠️ **This is a first move with a stated review condition, not a terminus.** The named risk is that a
 * flag nobody reads becomes one more prose branch wearing a type. So: once inconclusive findings are
 * emitted by a meaningful population of audits, re-examine whether a floor should see them — with the
 * call-site evidence this flag was chosen to generate. Until then, no gate.
 */
class Finding
{
    /**
     * @param  bool  $conclusive  whether the check measured its subject; false when its population was
     *                            empty or unreachable, so the status says "nothing here" rather than
     *                            "measured clean"
     */
    public function __construct(
        public DoctorStatus $status,
        public string $check,
        public string $detail,
        public bool $conclusive = true,
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

    /**
     * A check that could NOT measure its subject — the population it reads was empty or unreachable, so
     * it has not found the subject clean, it has not seen the subject at all.
     *
     * Reports `Pass` by design: the audit found no defect, and a host that genuinely registers nothing on
     * this axis is genuinely clean, so this must not redden a build. The flag is what tells the two apart
     * for any reader that wants to know.
     *
     * `$detail` still NAMES the empty population in prose — "No particle operations are registered in this
     * host." — because the flag makes the distinction machine-readable, it does not make it self-explaining.
     *
     * A different status may be paired with the flag where an audit's inconclusiveness is itself worth
     * warning about; construct directly for that, since the honest default for "nothing to measure" is Pass.
     */
    public static function inconclusive(string $check, string $detail): self
    {
        return new self(DoctorStatus::Pass, $check, $detail, conclusive: false);
    }
}
