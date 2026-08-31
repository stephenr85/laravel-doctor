<?php

namespace Rushing\Doctor;

/**
 * Renders a {@see DoctorReport} as lines, consistently, so every doctor command stops hand-rolling its own
 * output.
 *
 * Writes through a plain `callable(string): void` rather than a console class. A command passes
 * `$this->line(...)` and adopts this without changing its findings or its own signature; a test passes a
 * closure and asserts on the lines. Taking an OutputStyle here would couple a moat-free foundation primitive
 * to the console component for no gain.
 *
 * Renders {@see Finding::$conclusive} as a distinct `[----]` badge. A finding that measured nothing keeps
 * its real status everywhere a floor can see it and loses `[PASS]` only in the operator's eye-line, which
 * is where the false green was actually being read.
 */
class DoctorRenderer
{
    /** @param  callable(string): void  $write */
    public function __construct(private $write) {}

    public function render(DoctorReport $report): void
    {
        foreach ($report->findings as $finding) {
            ($this->write)(sprintf('%s %s — %s', $this->marker($finding->status, $finding->conclusive), $finding->check, $finding->detail));
        }

        $counts = $report->counts();

        ($this->write)(sprintf(
            '%d passed, %d warned, %d failed.',
            $counts[DoctorStatus::Pass->value],
            $counts[DoctorStatus::Warn->value],
            $counts[DoctorStatus::Fail->value],
        ));

        // Appended only when non-zero, so a report of conclusive findings renders byte-for-byte what it
        // always did — the counts line is read by eye and by CI, and a new always-on clause would be a
        // change to both for the sake of a number that is usually 0.
        $inconclusive = count($report->inconclusive());

        if ($inconclusive > 0) {
            ($this->write)(sprintf(
                '%d of those measured nothing — an empty or unreachable population, not a clean one.',
                $inconclusive,
            ));
        }
    }

    /**
     * A check that measured nothing gets its own marker rather than its status's, because the whole point of
     * {@see Finding::$conclusive} is that `[PASS]` over an empty population is the false green. The marker
     * displaces the status only in the badge; the status itself is untouched and still drives every floor.
     */
    private function marker(DoctorStatus $status, bool $conclusive = true): string
    {
        if (! $conclusive) {
            return '[----]';
        }

        return match ($status) {
            DoctorStatus::Pass => '[PASS]',
            DoctorStatus::Warn => '[WARN]',
            DoctorStatus::Fail => '[FAIL]',
        };
    }
}
