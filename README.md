# schemastud/laravel-doctor

Moat-free **doctor-audit primitives** — the shared vocabulary every readiness doctor reports in,
owned by the foundation vendor (`schemastud/*`) with **zero dependency on the product/moat**
(`splicewire/laravel-satellite`).

Two primitives:

- **`Schemastud\Doctor\DoctorStatus`** — the `Pass` / `Warn` / `Fail` backed enum.
- **`Schemastud\Doctor\Finding`** — one readiness-check result: a `DoctorStatus`, the check name,
  and a human-readable detail. Built via `Finding::pass()` / `warn()` / `fail()` or directly.

```php
use Schemastud\Doctor\Finding;

$findings = [
    Finding::pass('php', 'PHP 8.3 present'),
    Finding::warn('cache', 'file driver — Redis recommended in production'),
    Finding::fail('pgvector', 'extension not installed'),
];
```

## Why it exists (ADR-0095)

These primitives used to live in the paid `splicewire/laravel-satellite` package, which forced any
foundation-layer doctor (the free-tier **Beam** readiness doctor, `schemastud/laravel-beam-commerce`)
to require the moat just to self-diagnose. **ADR-0095** relocates them to this moat-free foundation
package so both the free Beam doctor and the paid satellite doctor consume them from a common,
product-free home. `splicewire/laravel-satellite` keeps a deprecated re-export shim at the old paths
so existing consumers compile unchanged until they migrate their imports (dropped in a later step).

## Scope

Primitives only. A shared doctor runner/renderer may join later; for now each host composes its own
audit list out of `Finding`s. No config, no service bindings — the `ServiceProvider` is just the
Laravel entry point.
