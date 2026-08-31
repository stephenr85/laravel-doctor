> You are in **rushing/laravel-doctor** — moat-free doctor-audit primitives: the `Finding` value object and the Pass/Warn/Fail `DoctorStatus` vocabulary that every readiness doctor (foundation Beam and the paid satellite) reports in.

Foundation-vendor, zero product dependency (ADR-0095).

## Gate or advisory — when a finding may stop a build

`DoctorRegistration::$gate` is the flag that decides whether a `Fail` reddens an exit code, and the
default (`false`) is a ruling, not an accident. The rule: **a finding may stop a build only when the
declaration's author could have gotten it right without knowing which host would load it, and only
over a population that opted in.** A fact about the *host* is an advisory finding — never a fatal,
never a boot-time throw. Enforcement may be a function of host *composition* and never of the
*environment* the check ran in. A gate must not sit behind a `require-dev` `interface_exists()`, and a
gate audit that cannot run reports `Fail`, because unverified is not passed.

Three axes, the corollaries, and the estate census are in
`docs/agents/gate-or-advisory.convention.md`.

## Measured clean, or measured nothing?

`Finding::$conclusive` is the sibling flag: an audit whose population is empty or unreachable emits
`Finding::inconclusive()` rather than `Finding::pass()`, because it has not found its subject clean —
it has not seen its subject. Like `$gate` it sits **off** `DoctorStatus` (ruled 2026-08-30,
api-surface-coherence 124): an inconclusive finding still reports `Pass`, so no floor, no `worst()`,
no `counts()` and no exit code moves. Read the population with `DoctorReport::inconclusive()`.
