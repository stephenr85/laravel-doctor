<?php

use RuntimeException;
use Rushing\Doctor\AuditError;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRenderer;
use Rushing\Doctor\DoctorReport;
use Rushing\Doctor\DoctorRunner;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;

/**
 * api-surface-coherence ticket 124 — a registry-side audit reported PASS over an EMPTY registry, so
 * "clean" and "not measured here" were the same green.
 *
 * Ruled 2026-08-30, option C: conclusiveness is a FLAG on the finding, off the DoctorStatus enum,
 * additive and exit-code-neutral. These tests pin BOTH halves of that ruling — that the distinction is
 * now readable, and that reading it changes no floor, no `worst()`, no `counts()` and no exit code.
 * The second half is the ticket's acceptance criterion 2: the exit-code behaviour is stated and tested,
 * and what it is stated to be is "unchanged".
 */

// ── fixtures ──────────────────────────────────────────────────────────────

class EmptyPopulationAudit implements DoctorAudit
{
    public function run(): array
    {
        return [Finding::inconclusive('registry.ops', 'No particle operations are registered in this host.')];
    }
}

// ── the flag ──────────────────────────────────────────────────────────────

it('reports an inconclusive finding as a Pass that is flagged, not as a fourth status', function () {
    $finding = Finding::inconclusive('registry.ops', 'No particle operations are registered in this host.');

    expect($finding->status)->toBe(DoctorStatus::Pass);
    expect($finding->conclusive)->toBeFalse();
    expect($finding->check)->toBe('registry.ops');
    expect($finding->detail)->toBe('No particle operations are registered in this host.');
});

it('leaves every other factory conclusive, so nothing already emitted changes meaning', function () {
    expect(Finding::pass('db', 'connected')->conclusive)->toBeTrue();
    expect(Finding::warn('cache', 'stale')->conclusive)->toBeTrue();
    expect(Finding::fail('queue', 'down')->conclusive)->toBeTrue();
    expect((new Finding(DoctorStatus::Fail, 'ext', 'missing pgvector'))->conclusive)->toBeTrue();
});

it('can pair the flag with any status, since conclusiveness is orthogonal to severity', function () {
    $warned = new Finding(DoctorStatus::Warn, 'registry.ops', 'unreachable', conclusive: false);

    expect($warned->status)->toBe(DoctorStatus::Warn);
    expect($warned->conclusive)->toBeFalse();
});

// ── the read side ─────────────────────────────────────────────────────────

it('reads the inconclusive population back off the report', function () {
    $report = new DoctorReport([
        Finding::pass('a', 'measured clean'),
        Finding::inconclusive('b', 'nothing here'),
        Finding::warn('c', 'a smell'),
        Finding::inconclusive('d', 'nothing here either'),
    ]);

    expect(array_map(fn (Finding $f) => $f->check, $report->inconclusive()))->toBe(['b', 'd']);
});

// ── exit-code neutrality: the ruling's load-bearing half ──────────────────

it('folds an inconclusive finding into the passes for counts() and worst()', function () {
    $report = new DoctorReport([
        Finding::pass('a', 'measured clean'),
        Finding::inconclusive('b', 'nothing here'),
    ]);

    expect($report->counts())->toBe([
        DoctorStatus::Pass->value => 2,
        DoctorStatus::Warn->value => 0,
        DoctorStatus::Fail->value => 0,
    ]);
    expect($report->worst())->toBe(DoctorStatus::Pass);
});

it('does not meet a floor, even at the strictest one, even on a GATE registration', function () {
    $runner = new DoctorRunner(app());

    foreach ([DoctorStatus::Pass, DoctorStatus::Warn, DoctorStatus::Fail] as $floor) {
        // Pass floor is the strictest the vocabulary has: at `--floor=pass` a plain Pass DOES block.
        // An inconclusive finding is a Pass, so it blocks there too — and that is the correct, stated
        // behaviour, not an oversight: the flag adds no severity and removes none.
        $report = null;
        $threw = false;

        try {
            $report = $runner->run([new DoctorRegistration('acme/pkg', EmptyPopulationAudit::class, gate: true)], $floor);
        } catch (Throwable $e) {
            $threw = true;
        }

        expect($threw)->toBe($floor === DoctorStatus::Pass);

        if (! $threw) {
            expect($report->worst())->toBe(DoctorStatus::Pass);
            expect($report->inconclusive())->toHaveCount(1);
        }
    }
});

// ── rendering: where the false green was actually being read ──────────────

it('renders an inconclusive finding with its own badge and a summary clause', function () {
    $lines = [];
    (new DoctorRenderer(function (string $line) use (&$lines) {
        $lines[] = $line;
    }))->render(new DoctorReport([
        Finding::pass('a', 'measured clean'),
        Finding::inconclusive('b', 'No particle operations are registered in this host.'),
    ]));

    expect($lines)->toBe([
        '[PASS] a — measured clean',
        '[----] b — No particle operations are registered in this host.',
        '2 passed, 0 warned, 0 failed.',
        '1 of those measured nothing — an empty or unreachable population, not a clean one.',
    ]);
});

it('renders a wholly conclusive report byte-for-byte as it always did', function () {
    $lines = [];
    (new DoctorRenderer(function (string $line) use (&$lines) {
        $lines[] = $line;
    }))->render(new DoctorReport([
        Finding::pass('a', 'measured clean'),
        Finding::fail('b', 'broken'),
    ]));

    expect($lines)->toBe([
        '[PASS] a — measured clean',
        '[FAIL] b — broken',
        '1 passed, 0 warned, 1 failed.',
    ]);
});

/*
|--------------------------------------------------------------------------
| api-surface-coherence 128 — the sweep's two rulings
|--------------------------------------------------------------------------
*/

it('flags an audit that could not run as inconclusive on BOTH arms, without moving its status', function () {
    $advisory = AuditError::running(stdClass::class, gate: false, e: new RuntimeException('boom'));
    $gated = AuditError::running(stdClass::class, gate: true, e: new RuntimeException('boom'));

    // An audit that threw did not measure its subject — that is what it IS — so both arms carry the flag.
    expect($advisory->conclusive)->toBeFalse()
        ->and($gated->conclusive)->toBeFalse();

    // …and the severity ruling is untouched: advisory warns, a gate that could not verify still fails.
    expect($advisory->status)->toBe(DoctorStatus::Warn)
        ->and($gated->status)->toBe(DoctorStatus::Fail);
});

it('reports the audit-errored findings in the inconclusive population, which is the point of flagging them', function () {
    $report = new DoctorReport([
        Finding::pass('a', 'clean'),
        AuditError::running(stdClass::class, gate: true, e: new RuntimeException('boom')),
    ]);

    expect($report->inconclusive())->toHaveCount(1)
        ->and($report->inconclusive()[0]->check)->toBe(AuditError::CHECK)
        ->and($report->worst())->toBe(DoctorStatus::Fail); // still gates exactly as before
});

it('displaces the badge only for a Pass, so an inconclusive Warn or Fail never hides its severity', function () {
    $lines = [];
    $renderer = new DoctorRenderer(function (string $line) use (&$lines) {
        $lines[] = $line;
    });

    $renderer->render(new DoctorReport([
        Finding::inconclusive('empty', 'nothing to measure'),
        new Finding(DoctorStatus::Warn, 'warned', 'measured nothing, and that is worth a warning', conclusive: false),
        AuditError::running(stdClass::class, gate: true, e: new RuntimeException('boom')),
    ]));

    $rendered = implode("\n", $lines);

    expect($rendered)->toContain('[----] empty')
        ->and($rendered)->toContain('[WARN] warned')      // NOT [----] — the severity survives
        ->and($rendered)->toContain('[FAIL] '.AuditError::CHECK)
        ->and($rendered)->toContain('3 of those measured nothing');
});
