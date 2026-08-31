# Convention — gate or advisory: when a finding may stop a build

**Status:** standing rule. Written 2026-08-26 from the ruling on `beam-facade` ticket 119, against a
measured estate.
**Scope:** every `DoctorRegistration` in the fleet, every `surgeon:audit` built-in, and every
boot-time validation that was tempted to throw. Homed here because `DoctorRegistration::$gate` is
the flag, and the flag is this package's.

---

## The rule, in one line

**A finding may stop a build only when the declaration's author could have gotten it right without
knowing which host would load it, and only over a population that opted in.**

Everything else is an advisory finding: rendered, counted, ratcheted if you like — never fatal, never
a boot-time throw.

## The three axes, in order

### 1. Whose fact is it? — this decides *fatal vs finding*

- **A fact about the declaration** — grammar, a missing subject, a duplicate key, an undescribed
  registration. The author had everything needed to get it right. Eligible to gate.
- **A fact about the host** — "is this resource registered here?", "does this config key exist
  here?", "is this table present?". The author could not have known. **Advisory finding, never a
  fatal, and never a throw at boot.**

Measured the hard way: a new event catalog threw at boot when an event's resource prefix was not
registered — true at the flagship, false at another host, and that host **could not boot at all**
until the throw was downgraded to a doctor audit. Two details from the repair worth copying: the
entry stays **registered** (refusing it silently amputates the host's own declared vocabulary), and
the check is computed **on read**, never stamped at `register()`, so a resource registered later
clears it instead of recording load order as truth. The fleet-level statement of this axis is in the
ecosystem `AGENTS.md`, *"A check whose answer depends on the host must not throw"*; this document is
where it continues past the throw into the gate.

### 2. Did the population opt in? — this decides *gate vs advisory*

Eligible-to-gate is not sufficient. A registration may gate only when its population **opted in**:

- by declaring itself (an attribute — `#[IsRegistry]`),
- by being composed (installing an arm makes that arm's readiness the host's business),
- or by the host writing the registration itself.

A gate over a population that did not opt in blocks every host on day one and is switched off within
the hour. That is not a hypothetical: the morph-alias check covers every model in every installed
family package, many of which are legitimately never polymorphic, and it is registered advisory for
exactly this reason with a stated promotion trigger.

Two corollaries that have each already cost real time:

- **A foundation package may not declare a gate over roots that did not ask for it.** Severity is the
  audit's — it is what an operator reads. Gating is the host's — it is what stops a build. Surgeon's
  built-in channel is therefore **advisory by contract at any severity**, with a per-audit manifest
  opt-in that carries **the flag only**, never a replacement instance. The rejected alternative — a
  per-audit `GATES` constant — would let a foundation package decide, for every root in an estate,
  what fails that root's build.
- **A judgement call may not gate.** If the check carries a whitelist, a heuristic, or a "shaped
  like" predicate, it ratchets against a committed artifact; it does not gate. *A judgement call that
  fails the build is a judgement someone else made for you.* Where a mechanical half and a judgement
  half both exist, **split them into two audits and register them apart** — that is what makes the
  split real rather than documentary, and it is the only thing that lets the mechanical half carry no
  suppression list at all.

### 3. What moved the number? — this decides *what the gate may look at*

**Enforcement may be a function of host composition. It may never be a function of the environment
the check ran in.**

Composition is a decision someone made and is readable off `composer.json` /
`vendor/composer/installed.json`. Environment is an accident of the invocation: `APP_ENV`,
`--no-dev`, whether config was cached, whether a debug bar was switched on, how stale a
remote-tracking ref is. A gate or a ratchet whose value moves with the second **cannot be compared
across two machines**, so it must exclude that population **by origin** and name what it excluded
inside the artifact.

Measured on the undeclared-surface ratchet, which had to split its population three ways: the host's
own code — in; production vendor and composed family packages — in, bucketed by origin, because
mounting them *was* the host's composition decision and dropping them would hide real surface behind
the word "vendor"; dev-only vendor rows — **out**, because `--no-dev` and `APP_ENV` move them.

Note the mechanics: attribute a row to its package by reading composer's manifest
(`install-path`, `dev-package-names`), **never by the path shape** — a co-dev overlay symlinks family
packages, so their files carry no `vendor/` segment and a path heuristic attributes the largest block
in the population to nothing.

## Two mechanics that follow

- **A gate must not be conditional on a dev-only dependency.** A gate registered behind
  `interface_exists()` on a `require-dev` interface is silently absent from every production host —
  the gate that only exists where you were already looking. Register gating audits **unconditionally**
  and let the audit itself report its own blindness (a "detection unavailable" finding) when the
  optional parser or client is missing. Reporting blindness is the price of being unconditional and it
  is the correct price: an empty work-list and a genuine pass are indistinguishable otherwise.
- **A gate audit that cannot run reports Fail; an advisory that cannot run reports Warn.** A gate's
  contract is that its subject was *verified*, and a gate that degrades to "no findings" is how a gate
  stops gating. **Unverified is not passed.** The severity there is the runner's, not the audit's.
  Never suppress the exception silently — a report that is green *because* an audit failed is strictly
  worse than the crash.

## The census — this is a posture, not an accumulation

Ticket 119 asked whether the estate's overwhelmingly-advisory shape was considered or accumulated,
"because nobody has ever had to argue for `gate: false` — it is what you get by not passing an
argument." Measured 2026-08-26 across every real package `src/` tree:

| | count |
| --- | --- |
| `gate: true` registrations, estate-wide | **7** |
| …declared by a package about its own composition | 7 |
| …declared by a **host** exercising the opt-in | **0** |

And at the flagship, `surgeon:audit --json`, same day: **79 audits, 1295 findings — 1216 `warn`,
72 `pass`, 7 `fail` — and 2 gating registrations.** Ticket 119's reading three days earlier was
64 / 874 / 4 / 2. The population grew by 15 audits and 421 findings and the number of gates did not
move: 62-of-64-advisory became 77-of-79-advisory. A posture that survives a 23% growth in its own
population unchanged is a posture.

The seven: `UndescribedRegistryAudit` and `RegistryConformanceAudit` (beam, doctor-side and
unconditional), and one readiness audit each in the analytics, commerce, mdx, ux-prototype and
satellite arm doctors. Every one of the seven carries an in-code justification for the exception, and
each justification is an instance of axis 2 — the arm audits gate a population that opted in by
*installing the arm*; the two registry audits gate a population that opted in by *declaring an
attribute* or *registering a binding*.

So the answer is: **considered, and undeclared.** The rule was already being applied correctly and
lived only in five separate docblocks; the defect ticket 119 found was discoverability, not posture.
The `gate: false` default is right and stays the default — this document is the argument for it, so
that from now on the burden falls where it belongs, on anyone reaching for `gate: true`.

The zero in the third row is the one live gap and it is **not** a defect: no host has needed a gate a
package did not already declare. If a host ever registers one, that registration is the host saying
"this check is mine now" — which is the mechanism working, not a drift.

## Three things this convention does not decide

- **How loud an advisory is.** Severity (Pass/Warn/Fail) is the audit's own call about what an
  operator should read, and it is orthogonal to gating: a gating audit's `warn` still fails the exit
  code, an advisory audit's `fail` never does.
- **Whether a check MEASURED anything.** A third orthogonal axis, added 2026-08-30 by the ruling on
  `api-surface-coherence` ticket 124 and carried by `Finding::$conclusive` /
  `Finding::inconclusive()`. An audit whose population is empty or unreachable has not found its
  subject clean — it has not seen its subject — and until the flag existed that said `Pass`, which is
  the same green as "measured clean". The flag is deliberately **off** `DoctorStatus`, for the same
  reason `$gate` is: "inconclusive" has no honest ordinal on a linear severity ladder, and the estate
  had already settled that an axis orthogonal to the floor belongs beside it rather than inside it.
  **Nothing gates on it**, by the same rule this document opens with — *"is this registry populated in
  this harness?"* is a fact about the **host**, so it is an advisory finding and never a fatal. Read
  the population with `DoctorReport::inconclusive()`; the review condition attached to 124's ruling is
  to revisit whether a floor should ever see it once enough audits emit it.
- **Whether a check should exist.** This is about the consequence of a finding, not its merit.
