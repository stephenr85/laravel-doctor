<?php

namespace Rushing\Doctor;

/**
 * The collected result of one doctor run: every {@see Finding} every audit reported, in run order.
 *
 * A report is a VALUE, not a verdict. It says what was found; whether that constitutes failure is the
 * runner's floor decision, because the same findings legitimately mean "report only" in a repo mid-migration
 * and "fail the build" in a converged one.
 */
class DoctorReport
{
    /**
     * @param  list<Finding>  $findings
     */
    public function __construct(public readonly array $findings) {}

    /** The most severe status reported, or Pass for an empty report. */
    public function worst(): DoctorStatus
    {
        $worst = DoctorStatus::Pass;

        foreach ($this->findings as $finding) {
            if ($finding->status->severity() > $worst->severity()) {
                $worst = $finding->status;
            }
        }

        return $worst;
    }

    /** @return list<Finding> */
    public function atLeast(DoctorStatus $floor): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (Finding $finding) => $finding->status->atLeast($floor),
        ));
    }

    /**
     * The findings that carry a suggested correction — so a caller can generate fixes without re-deriving
     * them. Their suggestions pass through untouched.
     *
     * @return list<FixableFinding>
     */
    public function fixable(): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (Finding $finding) => $finding instanceof FixableFinding && $finding->operation !== null,
        ));
    }

    /** @return array<string, int> status value → count, always carrying all three keys */
    public function counts(): array
    {
        $counts = [
            DoctorStatus::Pass->value => 0,
            DoctorStatus::Warn->value => 0,
            DoctorStatus::Fail->value => 0,
        ];

        foreach ($this->findings as $finding) {
            $counts[$finding->status->value]++;
        }

        return $counts;
    }
}
