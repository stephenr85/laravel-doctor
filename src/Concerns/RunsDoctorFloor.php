<?php

namespace Rushing\Doctor\Concerns;

use Rushing\Doctor\DoctorFailed;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRenderer;
use Rushing\Doctor\DoctorReport;
use Rushing\Doctor\DoctorRunner;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;

/**
 * The `--floor` plumbing every doctor command in the fleet was hand-copying: parse the option into a
 * {@see DoctorStatus}, run registrations through the shared {@see DoctorRunner} without letting a
 * {@see DoctorFailed} escape, and render each {@see Finding} as the one-line `<check>: <detail>` at
 * info (Pass) / warn (Warn) / error (Fail).
 *
 * This is NOT the shared {@see DoctorRenderer} the commands deliberately declined —
 * that renders a different format (`[PASS] check — detail` plus a summary line). The lines here are
 * the ones every command already printed, hoisted verbatim so adopting the trait changes no output
 * byte; each command keeps its own headline/epilogue and exit-code policy.
 *
 * For use inside an `Illuminate\Console\Command` whose signature declares a `--floor` option.
 */
trait RunsDoctorFloor
{
    /**
     * Parse `--floor` into a status, or render the invalid-value error and return null so the
     * command can bail with its own FAILURE.
     */
    protected function parseFloor(): ?DoctorStatus
    {
        $floor = DoctorStatus::tryFrom(strtolower((string) $this->option('floor')));

        if ($floor === null) {
            $this->components->error('Invalid --floor value; expected one of: pass, warn, fail.');
        }

        return $floor;
    }

    /**
     * Run the registrations at the floor, converting the runner's {@see DoctorFailed} throw back
     * into data: the full report (a failure still carries every finding, so a failed run is still a
     * complete work-list) plus whether a gate finding met the floor.
     *
     * @param  iterable<DoctorRegistration>  $registrations
     * @return array{DoctorReport, bool}
     */
    protected function runAtFloor(DoctorRunner $runner, iterable $registrations, DoctorStatus $floor): array
    {
        try {
            return [$runner->run($registrations, $floor), false];
        } catch (DoctorFailed $failure) {
            return [$failure->report, true];
        }
    }

    /**
     * One finding as the line every command already printed. A finding that measured nothing
     * ({@see Finding::$conclusive}) renders at its status's level — no gate, no exit-code change, that is
     * the ruling — but says so in the line, because the operator reading `check: detail` at info level is
     * exactly the reader the false green was fooling.
     */
    protected function renderFinding(Finding $finding): void
    {
        $line = $finding->conclusive
            ? $finding->check.': '.$finding->detail
            : $finding->check.' (measured nothing): '.$finding->detail;

        match ($finding->status) {
            DoctorStatus::Pass => $this->components->info($line),
            DoctorStatus::Warn => $this->components->warn($line),
            DoctorStatus::Fail => $this->components->error($line),
        };
    }

    /**
     * @param  iterable<Finding>  $findings
     */
    protected function renderFindings(iterable $findings): void
    {
        foreach ($findings as $finding) {
            $this->renderFinding($finding);
        }
    }
}
