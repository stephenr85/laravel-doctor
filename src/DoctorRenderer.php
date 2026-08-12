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
 */
class DoctorRenderer
{
    /** @param  callable(string): void  $write */
    public function __construct(private $write) {}

    public function render(DoctorReport $report): void
    {
        foreach ($report->findings as $finding) {
            ($this->write)(sprintf('%s %s — %s', $this->marker($finding->status), $finding->check, $finding->detail));
        }

        $counts = $report->counts();

        ($this->write)(sprintf(
            '%d passed, %d warned, %d failed.',
            $counts[DoctorStatus::Pass->value],
            $counts[DoctorStatus::Warn->value],
            $counts[DoctorStatus::Fail->value],
        ));
    }

    private function marker(DoctorStatus $status): string
    {
        return match ($status) {
            DoctorStatus::Pass => '[PASS]',
            DoctorStatus::Warn => '[WARN]',
            DoctorStatus::Fail => '[FAIL]',
        };
    }
}
