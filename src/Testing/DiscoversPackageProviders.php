<?php

namespace Rushing\Doctor\Testing;

use Composer\Autoload\ClassLoader;
use ReflectionClass;
use RuntimeException;
use Rushing\PackageTopology\Query\PackageJsonQuery;

/**
 * TESTBENCH PROVIDER DISCOVERY — the boot list a host would have assembled,
 * read off the installed manifests instead of hand-maintained.
 *
 * ```php
 * final class TestCase extends \Orchestra\Testbench\TestCase
 * {
 *     use \Rushing\Doctor\Testing\DiscoversPackageProviders;
 *
 *     protected function getPackageProviders($app): array
 *     {
 *         return $this->discoveredProviders();
 *     }
 * }
 * ```
 *
 * WHY THIS LIVES IN A DOCTOR PACKAGE, AND NOT IN A TESTING ONE. The rest of
 * `rushing/laravel-doctor` is a diagnostics vocabulary — {@see \Rushing\Doctor\Finding},
 * {@see \Rushing\Doctor\DoctorStatus}, {@see \Rushing\Doctor\DoctorRunner} — and this is
 * a trait for test harnesses, so on the surface it is in the wrong house. It is not.
 * The defect it ends is *a suite lying about what is booted*, and "is the thing I am
 * measuring actually running?" is a diagnosis, not a test utility. Every other audit in
 * this package answers a question about the host; this one answers the same class of
 * question about the harness that is auditing the host — the instrument's own readiness
 * check. A green suite over unbooted providers is precisely the failure mode a doctor
 * exists to name, and it is the one failure mode no doctor could previously see, because
 * it disables the environment the doctor itself would run in.
 *
 * THE DEFECT. A Laravel host reads every installed package's `extra.laravel.providers`
 * and registers them (package auto-discovery). **Testbench does not.** It boots exactly
 * what `getPackageProviders()` names and nothing else. That would be merely inconvenient
 * if an unregistered provider produced an error — but it usually does not, because the
 * class the provider *would* have bound is still **auto-resolvable**: the container
 * reflects on it, finds a constructor it can satisfy from defaults, and hands back a
 * fresh, unbound, default-constructed instance. The suite stays green and the object is
 * empty. Six measured instances, all of them silent:
 *
 * - `PopcornServiceProvider` — `make(RegistryIndex::class)` returned a FRESH index per
 *   call, so everything the suite described wrote to an object nobody read.
 * - `LaravelDataServiceProvider` — `config('data')` was `null`, fatalling every
 *   `validateAndCreate()` across 69 input-data classes.
 * - `LaravelDataSchemasServiceProvider` — the nastiest, because it did not fail, it
 *   ANSWERED: `SchemaIdResolver` stayed auto-resolvable and its nullable `$baseUri`
 *   allowed null, so it declared no authority while the config held the right value.
 * - `SurgeonServiceProvider` — `require-dev` does not cascade, and installed-but-
 *   unregistered is indistinguishable from not-installed from inside a suite, so a
 *   whole review gate was skipped for months.
 * - `BeamCalendarsServiceProvider` — surfaced as `Target [ChannelSource] is not
 *   instantiable`, naming a class and saying nothing about providers.
 * - `CapabilityRegistry` (beam, via an umbrella package that registered only itself) —
 *   the singleton binding never happened, so the provider seeded one registry and the
 *   consumer read another.
 *
 * 53 packages in this estate hand-list their providers, up to 20 apiece. Every one of
 * those lists rots silently as dependencies change, and nothing reports the rot.
 *
 * THE VOCABULARY IS SPELLED HERE ON PURPOSE. The reading is delegated to
 * {@see PackageJsonQuery}, which is deliberately framework-free — it takes a JSONPath
 * and knows nothing about Laravel. `$.extra.laravel.providers` is the CALLER's
 * vocabulary, and this trait is the caller: a Laravel-tier package is the correct place
 * for the string to be written down, which is exactly why the trait is here and not
 * there. What the query supplies that nothing else can is the ORDER — its answers come
 * back topologically sorted over the require graph, requirements before dependents, so a
 * package that seeds a registry boots before the package that overrides it. Composer's
 * own file order, `installed.json`, and a `glob()` over `vendor/` all answer *which* and
 * none of them answer *in what order*.
 *
 * THE ONE PROVIDER DISCOVERY CAN NEVER FIND IS YOUR OWN. A package is not installed
 * into its own `vendor/`, so the repo under test contributes nothing here — which is
 * why `$also` exists and why the adapter in a package suite almost always reads
 * `$this->discoveredProviders(also: [MyServiceProvider::class])`. Measured against
 * `splicewire/tower`: discovery reproduced five of the seven providers that file lists
 * by hand and the two it missed were both tower's own. That is the correct and complete
 * gap, not a shortfall — it is also why `$also` APPENDS: a root package composing an
 * estate wants to boot last, on top of everything it overrides.
 *
 * OPT-IN, NEVER A DEFAULT. This is a trait a suite reaches for, not behaviour a base
 * class imposes. Booting every provider a large package tree declares is not what every
 * suite wants — one package in this estate would boot 20 — and making it the default
 * would change the boot list of 53 suites at once, silently, which is the same class of
 * surprise the trait exists to end.
 *
 * TESTBENCH IS NOT IMPORTED. The method is `discoveredProviders()`, not
 * `getPackageProviders()`, so each consumer writes the one-line adapter above. That keeps
 * `orchestra/testbench` out of this package's dependencies entirely — the trait composes
 * onto a testbench case, a Pest `uses()`, or any harness at all.
 *
 * @see \Rushing\Doctor\Tests\Feature\DiscoversPackageProvidersTest
 */
trait DiscoversPackageProviders
{
    /**
     * The service providers declared by every installed, in-scope package, in
     * dependency order — requirements first.
     *
     * A package with no `extra.laravel.providers` is simply ABSENT. That is the
     * majority case, not the edge: most installed packages have no `extra` block at
     * all, and a discovery that threw on absence would be unusable for the question
     * it exists to answer.
     *
     * @param  list<class-string>  $also  providers to append after the discovered set — for a
     *                                    provider no manifest declares (a test-only provider, or
     *                                    one the suite needs registered last so it can override)
     * @param  list<string>  $except  providers to drop. Exact class names, or `fnmatch` patterns
     *                                matched with `FNM_NOESCAPE` so a namespace backslash is
     *                                literal — `'Acme\Legacy\*'` works as written. Applied to the
     *                                discovered set AND to `$also`, so an exclusion cannot be
     *                                quietly defeated by an append
     * @return list<class-string>
     */
    protected function discoveredProviders(array $also = [], array $except = []): array
    {
        $discovered = $this->providerDiscoveryQuery()->query(
            $this->providerDiscoveryPath(),
        );

        $providers = [];

        foreach ([...$discovered, ...$also] as $provider) {
            if (! is_string($provider) || $provider === '') {
                continue;
            }

            $provider = ltrim($provider, '\\');

            if (in_array($provider, $providers, true) || $this->matchesAny($provider, $except)) {
                continue;
            }

            $providers[] = $provider;
        }

        /** @var list<class-string> */
        return $providers;
    }

    /**
     * The same answers keyed by the package that declared them, same order — for when
     * you need to know WHICH package contributed a provider (writing the exclusion, or
     * explaining a boot order to a reviewer).
     *
     * @return array<string, list<mixed>>
     */
    protected function discoveredProvidersByPackage(): array
    {
        return $this->providerDiscoveryQuery()->queryByPackage(
            $this->providerDiscoveryPath(),
        );
    }

    /**
     * The JSONPath read from each package's `composer.json`. Override only to read a
     * different declaration site; the Laravel convention is this one.
     */
    protected function providerDiscoveryPath(): string
    {
        return '$.extra.laravel.providers';
    }

    /**
     * The vendor globs discovery reads. The everything-glob is the deliberate default:
     * a host discovers across ALL installed packages, and narrowing to the estate's own
     * vendors would miss exactly the third-party providers that produced four of the
     * six measured instances above (`spatie/laravel-data` among them).
     *
     * Narrow it only when a suite genuinely wants a subset — and know that narrowing
     * re-opens the defect for everything outside the scope.
     *
     * @return list<string>
     */
    protected function providerDiscoveryScope(): array
    {
        return ['*/*'];
    }

    /**
     * The `vendor/` directory to read.
     *
     * Resolved from Composer's own running autoloader rather than from `base_path()`,
     * because under testbench `base_path()` points at the throwaway skeleton inside
     * `vendor/orchestra/testbench-core/laravel` — the one directory whose manifests are
     * NOT the ones the suite installed. Override when the suite must read a different
     * tree (a fixture, or another root's vendor).
     */
    protected function providerDiscoveryVendorPath(): string
    {
        $loader = (new ReflectionClass(ClassLoader::class))->getFileName();

        if ($loader === false) {
            throw new RuntimeException('Could not locate the Composer autoloader to resolve a vendor path; override providerDiscoveryVendorPath().');
        }

        // vendor/composer/ClassLoader.php → vendor/
        return dirname($loader, 2);
    }

    protected function providerDiscoveryQuery(): PackageJsonQuery
    {
        if (! class_exists(PackageJsonQuery::class)) {
            throw new RuntimeException(
                'Provider discovery needs rushing/php-package-topology. A require-dev does not cascade to '
                .'consumers, so install it in the suite that uses this trait: '
                .'composer require --dev rushing/php-package-topology',
            );
        }

        return new PackageJsonQuery(
            $this->providerDiscoveryVendorPath(),
            $this->providerDiscoveryScope(),
        );
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = ltrim($pattern, '\\');

            // FNM_NOESCAPE is load-bearing, not tidiness. Without it `fnmatch` reads a
            // backslash as an ESCAPE, so `'Acme\*'` means "a literal asterisk" and matches
            // nothing — and every pattern a caller could plausibly write here is a PHP
            // namespace, i.e. backslashes all the way down. Measured: the pattern-exclusion
            // test failed on exactly this before the flag went in.
            if ($subject === $pattern || fnmatch($pattern, $subject, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }
}
