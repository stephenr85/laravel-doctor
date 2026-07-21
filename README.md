# rushing/laravel-doctor

Generic **doctor-audit primitives** — the shared vocabulary every readiness doctor reports in, with
**zero dependency on any product**. A generic package-development primitive (hence the personal
`rushing/*` namespace), consumed by both the free-tier Beam readiness doctor and the
`splicewire/laravel-satellite` doctor.

Two primitives:

- **`Rushing\Doctor\DoctorStatus`** — the `Pass` / `Warn` / `Fail` backed enum.
- **`Rushing\Doctor\Finding`** — one readiness-check result: a `DoctorStatus`, the check name,
  and a human-readable detail. Built via `Finding::pass()` / `warn()` / `fail()` or directly.

```php
use Rushing\Doctor\Finding;

$findings = [
    Finding::pass('php', 'PHP 8.3 present'),
    Finding::warn('cache', 'file driver — Redis recommended in production'),
    Finding::fail('pgvector', 'extension not installed'),
];
```

## Why it exists (ADR-0095)

These primitives used to live in the `splicewire/laravel-satellite` package, which forced any
base-layer doctor (the free-tier **Beam** readiness doctor, `schemastud/laravel-beam-commerce`) to
require the whole product just to self-diagnose. **ADR-0095** relocates them to this generic,
product-free package so both the free Beam doctor and the satellite doctor consume them from a common
home. (Originally extracted under `schemastud/`; later moved to the `rushing/*` namespace since the
primitives are generic package-development scaffolding, not foundation-specific.)

## Scope

Primitives only. A shared doctor runner/renderer may join later; for now each host composes its own
audit list out of `Finding`s. No config, no service bindings — the `ServiceProvider` is just the
Laravel entry point.
