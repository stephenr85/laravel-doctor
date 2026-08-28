<?php

use Rushing\Doctor\Testing\DiscoversPackageProviders;

/**
 * Discovery is exercised against `tests/Fixtures/vendor`, a tree whose manifests this
 * repo controls — never against the live `vendor/`, which moves whenever anything is
 * installed and would make these assertions a moving target rather than a proof.
 */
function discovery(array $also = [], array $except = [], array $scope = ['*'.'/'.'*']): array
{
    $harness = new class($scope)
    {
        use DiscoversPackageProviders {
            discoveredProviders as public;
            discoveredProvidersByPackage as public;
        }

        public function __construct(private array $scope) {}

        protected function providerDiscoveryVendorPath(): string
        {
            return __DIR__.'/../Fixtures/vendor';
        }

        protected function providerDiscoveryScope(): array
        {
            return $this->scope;
        }
    };

    return [$harness, $harness->discoveredProviders($also, $except)];
}

it('discovers every declared provider and nothing from a package that declares none', function () {
    [, $providers] = discovery();

    expect($providers)->toEqualCanonicalizing([
        'Acme\Kernel\KernelServiceProvider',
        'Acme\Engine\EngineServiceProvider',
        'Acme\Umbrella\UmbrellaServiceProvider',
        'Zeta\Leaf\LeafServiceProvider',
    ]);
});

it('orders requirements before dependents, which is NOT the alphabetical order', function () {
    [, $providers] = discovery();

    $at = fn (string $p) => array_search($p, $providers, true);

    // acme/umbrella requires acme/engine requires acme/kernel — a provider that seeds a
    // registry must boot before the provider that overrides it. This is the whole reason
    // discovery rides the require graph rather than a glob over vendor/.
    expect($at('Acme\Kernel\KernelServiceProvider'))->toBeLessThan($at('Acme\Engine\EngineServiceProvider'));
    expect($at('Acme\Engine\EngineServiceProvider'))->toBeLessThan($at('Acme\Umbrella\UmbrellaServiceProvider'));

    $alphabetical = $providers;
    sort($alphabetical);
    expect($providers)->not->toBe($alphabetical);
});

it('de-duplicates, keeping the position the dependency contributed', function () {
    // acme/umbrella re-declares acme/kernel's provider. It must appear once, at the
    // EARLY (kernel) position — a re-contribution by a dependent must not drag a
    // dependency's provider later in the boot order.
    [, $providers] = discovery();

    expect(array_count_values($providers)['Acme\Kernel\KernelServiceProvider'])->toBe(1);
    expect(array_search('Acme\Kernel\KernelServiceProvider', $providers, true))
        ->toBeLessThan(array_search('Acme\Umbrella\UmbrellaServiceProvider', $providers, true));
});

it('appends the providers a suite adds, after the discovered set', function () {
    [, $providers] = discovery(also: ['Local\TestOnlyServiceProvider']);

    expect($providers)->toHaveCount(5)
        ->and(end($providers))->toBe('Local\TestOnlyServiceProvider');
});

it('excludes by exact class name and by pattern', function () {
    [, $exact] = discovery(except: ['Acme\Umbrella\UmbrellaServiceProvider']);
    expect($exact)->not->toContain('Acme\Umbrella\UmbrellaServiceProvider')->toHaveCount(3);

    [, $pattern] = discovery(except: ['Acme\*']);
    expect($pattern)->toBe(['Zeta\Leaf\LeafServiceProvider']);
});

it('excludes an added provider too, so an exclusion cannot be defeated by an append', function () {
    [, $providers] = discovery(also: ['Acme\Extra\ExtraServiceProvider'], except: ['Acme\Extra\*']);

    expect($providers)->not->toContain('Acme\Extra\ExtraServiceProvider');
});

it('tolerates a leading backslash on both sides', function () {
    [, $providers] = discovery(also: ['\\Local\\Slashed'], except: ['\\Acme\\Kernel\\KernelServiceProvider']);

    expect($providers)->toContain('Local\Slashed')
        ->not->toContain('Acme\Kernel\KernelServiceProvider');
});

it('narrows to the declared scope, which re-opens the defect outside it', function () {
    [, $providers] = discovery(scope: ['acme/*']);

    expect($providers)->not->toContain('Zeta\Leaf\LeafServiceProvider')->toHaveCount(3);
});

it('reports which package contributed each provider', function () {
    [$harness] = discovery();

    $byPackage = $harness->discoveredProvidersByPackage();

    expect($byPackage)->toHaveKey('acme/kernel')
        ->and($byPackage['acme/umbrella'])->toBe([
            'Acme\Umbrella\UmbrellaServiceProvider',
            'Acme\Kernel\KernelServiceProvider',
        ])
        // acme/quiet declares no providers. Absence is absence, never an error, and it
        // is the majority case in any real vendor tree.
        ->and($byPackage)->not->toHaveKey('acme/quiet');
});
