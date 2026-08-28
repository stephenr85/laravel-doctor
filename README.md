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

## Testbench provider discovery (opt-in)

`Rushing\Doctor\Testing\DiscoversPackageProviders` builds the boot list a **host** would have
assembled — every installed package's `extra.laravel.providers`, in dependency order — so a testbench
suite stops hand-maintaining one.

**The defect it ends.** A Laravel host auto-discovers providers; **testbench does not**. It boots
exactly what `getPackageProviders()` names. That would be survivable if an unregistered provider threw
— but usually it doesn't, because the class it *would* have bound is still **auto-resolvable**: the
container reflects on it and hands back a fresh, unbound, default-constructed instance. **Green suite,
empty object.** Six measured instances in this estate, including a resolver that declared no authority
while the config held the right value, and a registry the provider seeded and the consumer never read.
53 packages here hand-list their providers, up to 20 apiece; every list rots silently.

```php
use Rushing\Doctor\Testing\DiscoversPackageProviders;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use DiscoversPackageProviders;

    protected function getPackageProviders($app): array
    {
        return $this->discoveredProviders(also: [MyOwnServiceProvider::class]);
    }
}
```

**It is opt-in, and it must stay that way.** Booting everything a large tree declares is not what every
suite wants, and a base-class default would change 53 suites' boot lists at once — the same class of
surprise the trait exists to end.

**Testbench is not a dependency of this package.** The method is `discoveredProviders()`, not
`getPackageProviders()`, so each consumer writes the one-line adapter above and `orchestra/testbench`
stays out of the manifest.

### Amending the result

| | |
|---|---|
| `also: [Foo::class]` | appended **after** the discovered set — for a provider no manifest declares, or one that must boot last so it can override. **Your own package's provider always goes here**: a package is not installed into its own `vendor/`, so discovery can never see it. |
| `except: ['Acme\Legacy\*']` | dropped. Exact class names or `fnmatch` patterns, matched with `FNM_NOESCAPE` so a namespace backslash is literal. Applied to `also` too, so an exclusion can't be defeated by an append. |
| `discoveredProvidersByPackage()` | the same answers keyed by the package that declared them — for writing the exclusion, or explaining a boot order. |

Four `protected` seams override the rest: `providerDiscoveryPath()`, `providerDiscoveryScope()`,
`providerDiscoveryVendorPath()`, `providerDiscoveryQuery()`.

### What it reads, and whose vocabulary that is

Discovery delegates to [`rushing/php-package-topology`](https://github.com/stephenr85/php-package-topology)'s
`PackageJsonQuery`, which is deliberately **framework-free**: it takes a JSONPath and knows nothing about
Laravel. **`$.extra.laravel.providers` is the caller's vocabulary** — a *composer* convention that a
*Laravel* caller happens to care about — and this trait is that caller. Spelling the string in a
Laravel-tier package is the whole reason the trait lives here and not there. Override
`providerDiscoveryPath()` to read a different declaration site.

What the query supplies that nothing else can is the **order**: answers come back topologically sorted
over the require graph, requirements before dependents, so a package that seeds a registry boots before
the package that overrides it. `installed.json`, composer's file order, and a `glob()` over `vendor/`
all answer *which* packages and none of them answer *in what order*.

`providerDiscoveryScope()` defaults to every vendor, because a host discovers across everything —
narrowing it re-opens the defect for whatever falls outside, and four of the six measured instances came
from third-party vendors. **A package with no `extra.laravel.providers` is simply absent, never an
error**; that is the majority case in any real tree.

### Installing it

The trait needs `rushing/php-package-topology`, which is a **`require-dev`** here — and a `require-dev`
does not cascade. Install it in the suite that uses the trait:

```bash
composer require --dev rushing/php-package-topology
```

`discoveredProviders()` raises a `RuntimeException` naming that command if the package is absent, rather
than fatalling on a missing class.

## Scope

Primitives only. A shared doctor runner/renderer may join later; for now each host composes its own
audit list out of `Finding`s. No config, no service bindings — the `ServiceProvider` is just the
Laravel entry point.

The one deliberate exception is `Testing/DiscoversPackageProviders` above. It is a test-harness trait in
a diagnostics package on purpose: the defect it ends is **a suite lying about what is booted**, and "is
the thing I am measuring actually running?" is a diagnosis, not a test utility — the instrument's own
readiness check, and the one failure mode no doctor could previously see, because it disables the
environment a doctor would run in.
