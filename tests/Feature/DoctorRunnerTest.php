<?php

use Rushing\Doctor\AuditError;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\DoctorFailed;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRenderer;
use Rushing\Doctor\DoctorRunner;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Rushing\Doctor\SuggestsFix;

/**
 * particle-doctrine-convergence ticket 07 — the runner this package was reserved for.
 *
 * The runner throws; audits stay pure reporters. That split is what lets ONE audit serve a doctor command,
 * a conformance sweep, and CI at three different severity floors.
 */

// ── fixtures ──────────────────────────────────────────────────────────────

class PassingAudit implements DoctorAudit
{
    public function run(): array
    {
        return [Finding::pass('passing', 'all good')];
    }
}

class WarningAudit implements DoctorAudit
{
    public function run(): array
    {
        return [Finding::warn('warning', 'a smell')];
    }
}

class FailingAudit implements DoctorAudit
{
    public function run(): array
    {
        return [Finding::fail('failing', 'broken')];
    }
}

/** A consumer-owned finding that opts into carrying a fix — the shape the marker interface is designed for. */
class SuggestingFinding extends Finding implements SuggestsFix
{
    public function __construct(string $check, string $detail, private mixed $suggestion)
    {
        parent::__construct(DoctorStatus::Fail, $check, $detail);
    }

    public function fixSuggestion(): mixed
    {
        return $this->suggestion;
    }
}

class FixableAudit implements DoctorAudit
{
    public function run(): array
    {
        return [new SuggestingFinding('fixable', 'broken but correctable', FixSuggestion::instance())];
    }
}

/** Stands in for a tool-owned fix suggestion; the runner must pass it through without reading it. */
class FixSuggestion
{
    public string $tier = 'guided';

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }
}

abstract class OrderRecordingAudit implements DoctorAudit
{
    /** @var list<string> */
    public static array $ran = [];
}

class FirstAudit extends OrderRecordingAudit
{
    public function run(): array
    {
        self::$ran[] = 'first';

        return [];
    }
}

class SecondAudit extends OrderRecordingAudit
{
    public function run(): array
    {
        self::$ran[] = 'second';

        return [];
    }
}

function runner(): DoctorRunner
{
    return new DoctorRunner(app());
}

function gated(string $audit, int $order = 100): DoctorRegistration
{
    return new DoctorRegistration(package: 'fixture', audit: $audit, gate: true, order: $order);
}

function advisory(string $audit, int $order = 100): DoctorRegistration
{
    return new DoctorRegistration(package: 'fixture', audit: $audit, gate: false, order: $order);
}

// ── collecting ────────────────────────────────────────────────────────────

it('executes registered audits and collects their findings', function () {
    $report = runner()->run([advisory(PassingAudit::class), advisory(WarningAudit::class)]);

    expect($report->findings)->toHaveCount(2)
        ->and($report->findings[0]->check)->toBe('passing')
        ->and($report->findings[1]->check)->toBe('warning');
});

it('reports the worst status it saw', function () {
    $report = runner()->run([advisory(PassingAudit::class), advisory(WarningAudit::class)]);

    expect($report->worst())->toBe(DoctorStatus::Warn);
});

it('runs lower-ordered audits first and keeps registration order on a tie', function () {
    OrderRecordingAudit::$ran = [];

    runner()->run([advisory(SecondAudit::class, order: 200), advisory(FirstAudit::class, order: 10)]);
    expect(OrderRecordingAudit::$ran)->toBe(['first', 'second']);

    OrderRecordingAudit::$ran = [];

    runner()->run([advisory(FirstAudit::class), advisory(SecondAudit::class)]);
    expect(OrderRecordingAudit::$ran)->toBe(['first', 'second']);
});

// ── the floor ─────────────────────────────────────────────────────────────

it('throws when a gate audit reports at or above the floor', function () {
    runner()->run([gated(FailingAudit::class)], DoctorStatus::Fail);
})->throws(DoctorFailed::class);

it('returns normally when findings sit below the floor', function () {
    $report = runner()->run([gated(WarningAudit::class)], DoctorStatus::Fail);

    expect($report->worst())->toBe(DoctorStatus::Warn);
});

it('lets the floor be lowered per invocation so the same audit can block on a warning', function () {
    // The same registration, the same findings — only the floor differs. A repo mid-migration reports; a
    // converged repo fails on regression.
    expect(fn () => runner()->run([gated(WarningAudit::class)], DoctorStatus::Warn))
        ->toThrow(DoctorFailed::class);

    expect(runner()->run([gated(WarningAudit::class)], DoctorStatus::Fail)->worst())
        ->toBe(DoctorStatus::Warn);
});

it('never throws for an advisory audit however severe the finding or low the floor', function () {
    // Advisory output is a backlog, and a backlog that fails the build is just a blocked build.
    $report = runner()->run([advisory(FailingAudit::class)], DoctorStatus::Pass);

    expect($report->worst())->toBe(DoctorStatus::Fail);
});

it('collects every finding before throwing, so a failure is still a full work-list', function () {
    try {
        runner()->run([advisory(WarningAudit::class), gated(FailingAudit::class)], DoctorStatus::Fail);
        throw new RuntimeException('expected DoctorFailed');
    } catch (DoctorFailed $failure) {
        expect($failure->report->findings)->toHaveCount(2)
            ->and($failure->blocking)->toHaveCount(1)
            ->and($failure->blocking[0]->check)->toBe('failing');
    }
});

// ── fixable findings + the no-promotion guarantee ─────────────────────────

it('carries the fixable findings on the thrown failure with their suggestions intact', function () {
    try {
        runner()->run([gated(FixableAudit::class)], DoctorStatus::Fail);
        throw new RuntimeException('expected DoctorFailed');
    } catch (DoctorFailed $failure) {
        $fixable = $failure->fixable();

        expect($fixable)->toHaveCount(1)
            // Same instance, not a copy: the runner passes the suggestion through rather than rebuilding it.
            ->and($fixable[0]->fixSuggestion())->toBe(FixSuggestion::instance())
            // And it never touched the tier — the guarantee is structural, since the runner cannot read it.
            ->and($fixable[0]->fixSuggestion()->tier)->toBe('guided');
    }
});

it('does not report a finding as fixable when it carries no suggestion', function () {
    $report = runner()->run([advisory(FailingAudit::class)]);

    expect($report->fixable())->toBeEmpty();
});

// ── rendering ─────────────────────────────────────────────────────────────

it('renders findings and a tally consistently', function () {
    $report = runner()->run([
        advisory(PassingAudit::class),
        advisory(WarningAudit::class),
        advisory(FailingAudit::class),
    ]);

    $lines = [];
    (new DoctorRenderer(function (string $line) use (&$lines) {
        $lines[] = $line;
    }))->render($report);

    expect($lines)->toBe([
        '[PASS] passing — all good',
        '[WARN] warning — a smell',
        '[FAIL] failing — broken',
        '1 passed, 1 warned, 1 failed.',
    ]);
});

it('counts every status including the ones with no findings', function () {
    $report = runner()->run([advisory(PassingAudit::class)]);

    expect($report->counts())->toBe(['pass' => 1, 'warn' => 0, 'fail' => 0]);
});

// ── the severity vocabulary ───────────────────────────────────────────────

it('orders the status vocabulary', function () {
    expect(DoctorStatus::Fail->atLeast(DoctorStatus::Warn))->toBeTrue()
        ->and(DoctorStatus::Warn->atLeast(DoctorStatus::Warn))->toBeTrue()
        ->and(DoctorStatus::Pass->atLeast(DoctorStatus::Warn))->toBeFalse();
});

// ── ticket 72 — a throwing audit is a finding, never the end of the command ────

class ThrowingAudit implements DoctorAudit
{
    public function run(): array
    {
        // The specimen shape: a query against a column a lagging database has not got, whose message
        // carries whole SQL over several lines.
        throw new RuntimeException("SQLSTATE[42S22]: Unknown column 'deleted_at'\nthe second line nobody needs");
    }
}

class UnconstructableAudit implements DoctorAudit
{
    public function __construct()
    {
        throw new RuntimeException('this audit cannot be built');
    }

    public function run(): array
    {
        return [];
    }
}

/** Registered in a manifest, resolvable, and not an audit at all — the same class of stale wiring. */
class NotAnAudit {}

it('turns a throwing audit into a finding and runs the rest of the manifest', function () {
    $report = runner()->run([advisory(ThrowingAudit::class), advisory(PassingAudit::class)]);

    // Before this, the throw escaped `run()` and PassingAudit's already-collected finding died with the
    // command — at exactly the roots whose report is worth reading.
    expect($report->findings)->toHaveCount(2)
        ->and($report->findings[1]->check)->toBe('passing');

    $errored = $report->findings[0];

    expect($errored->check)->toBe(AuditError::CHECK)
        ->and($errored->status)->toBe(DoctorStatus::Warn)
        ->and($errored->detail)->toContain(ThrowingAudit::class)
        ->and($errored->detail)->toContain('RuntimeException')
        ->and($errored->detail)->toContain("Unknown column 'deleted_at'")
        ->and($errored->detail)->not->toContain('the second line nobody needs'); // one line, bounded
});

it('reports a registration it cannot even resolve, one phase earlier and told apart', function () {
    $missing = runner()->run([advisory('Rushing\Doctor\Tests\NoSuchAudit')]);
    $unconstructable = runner()->run([advisory(UnconstructableAudit::class)]);
    $notAnAudit = runner()->run([advisory(NotAnAudit::class)]);

    expect($missing->findings[0]->check)->toBe(AuditError::CHECK_RESOLVE)
        ->and($missing->findings[0]->detail)->toContain('could not be resolved')
        ->and($unconstructable->findings[0]->check)->toBe(AuditError::CHECK_RESOLVE)
        ->and($unconstructable->findings[0]->detail)->toContain('this audit cannot be built')
        // A resolvable non-audit throws on the call, not on the make — reported as the run phase, which is
        // where the Error actually happened.
        ->and($notAnAudit->findings[0]->check)->toBe(AuditError::CHECK)
        ->and($notAnAudit->findings[0]->detail)->toContain('Error');
});

it('reports a throwing GATE audit as Fail, because an unverified gate is not a passed gate', function () {
    try {
        runner()->run([gated(ThrowingAudit::class)], DoctorStatus::Fail);
        throw new RuntimeException('expected DoctorFailed');
    } catch (DoctorFailed $failure) {
        // The point of the ticket: the error finding meets the floor test like any other, so it reaches
        // `$blocking` and the runner throws — it does not merely appear in the report.
        expect($failure->blocking)->toHaveCount(1)
            ->and($failure->blocking[0]->status)->toBe(DoctorStatus::Fail)
            ->and($failure->blocking[0]->detail)->toContain('unverified is not passed');
    }

    // …and an advisory registration never reddens anything on the strength of not knowing.
    expect(runner()->run([advisory(ThrowingAudit::class)], DoctorStatus::Pass)->worst())
        ->toBe(DoctorStatus::Warn);
});
