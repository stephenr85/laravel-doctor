<?php

namespace Rushing\Doctor;

/**
 * The collected result of one doctor run: every {@see Finding} every audit reported, in run order.
 *
 * A report is a VALUE, not a verdict. It says what was found; whether that constitutes failure is the
 * runner's floor decision, because the same findings legitimately mean "report only" in a repo mid-migration
 * and "fail the build" in a converged one.
 *
 * `counts()` and `worst()` read the STATUS and only the status, so they fold a check that measured nothing
 * into the passes — deliberately, since {@see Finding::$conclusive} is off the enum and changes no floor.
 * {@see inconclusive()} is where that distinction is read.
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
     * Currently returns an empty list for every caller: {@see SuggestsFix} has no implementors yet, by
     * decision — see its docblock. This is the seam's read side, kept for the first finding that opts in.
     *
     * @return list<Finding&SuggestsFix>
     */
    public function fixable(): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (Finding $finding) => $finding instanceof SuggestsFix && $finding->fixSuggestion() !== null,
        ));
    }

    /**
     * The findings that measured NOTHING — a check whose population was empty or unreachable, flagged via
     * {@see Finding::inconclusive()}. They are already in `findings` and already carry a real status, so
     * `worst()`, `counts()` and every floor are untouched by this method's existence; it is a read side, not
     * a filter the runner applies.
     *
     * Two callers it exists for. A CI summary that wants to say "12 passed, 4 of them measured nothing"
     * instead of reporting a green it cannot stand behind — which is the false green this flag was added to
     * end (api-surface-coherence 124). And the review condition attached to that ruling: whether a floor
     * should ever see inconclusive findings is to be re-examined against the population of audits that
     * actually emit them, and this is where that population is counted.
     *
     * @return list<Finding>
     */
    public function inconclusive(): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (Finding $finding) => ! $finding->conclusive,
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
