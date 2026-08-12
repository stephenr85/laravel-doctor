<?php

namespace Rushing\Doctor;

use Illuminate\Contracts\Container\Container;

/**
 * Runs registered audits, collects their findings, and THROWS above a configured severity floor.
 *
 * This is the piece the doctor foundation was reserved for and never had: every doctor command in the fleet
 * reimplemented its own pass/warn/fail loop, its own render, and its own exit-code logic, which is why the
 * same audit could not serve three callers at three strictnesses.
 *
 * Two invariants shape it:
 *
 *   1. **Audits stay pure reporters.** The runner throws; an audit only returns findings. That purity is what
 *      lets one audit serve a doctor command (report), a conformance sweep (suggest), and CI (fail) — an
 *      audit that threw would hardcode one of those three answers for all of them.
 *   2. **The floor is per invocation**, not per audit. A repo mid-migration reports without failing; a
 *      converged repo fails on regression. Same audits, same findings, different floor.
 *
 * The gate/advisory flag on a {@see DoctorRegistration} is orthogonal to the floor and still governs: an
 * advisory audit renders its findings and never blocks, no matter how severe or how low the floor. Advisory
 * output is a backlog, and a backlog that fails the build is just a blocked build.
 */
class DoctorRunner
{
    public function __construct(private Container $container) {}

    /**
     * @param  iterable<DoctorRegistration>  $registrations
     *
     * @throws DoctorFailed when a GATE audit reports at or above `$floor`
     */
    public function run(iterable $registrations, DoctorStatus $floor = DoctorStatus::Fail): DoctorReport
    {
        $findings = [];
        $blocking = [];

        foreach ($this->ordered($registrations) as $registration) {
            $audit = $this->container->make($registration->audit);

            foreach ($audit->run() as $finding) {
                $findings[] = $finding;

                if ($registration->gate && $finding->status->atLeast($floor)) {
                    $blocking[] = $finding;
                }
            }
        }

        $report = new DoctorReport($findings);

        if ($blocking !== []) {
            throw new DoctorFailed($blocking, $report, $floor);
        }

        return $report;
    }

    /**
     * Run order: lower `order` first, so a host's core audits render ahead of consumer registrations. Ties
     * keep registration order — `usort` is not stable across PHP versions for equal elements, so the index is
     * folded into the comparison rather than trusted.
     *
     * @param  iterable<DoctorRegistration>  $registrations
     * @return list<DoctorRegistration>
     */
    private function ordered(iterable $registrations): array
    {
        $indexed = [];

        foreach ($registrations as $registration) {
            $indexed[] = [count($indexed), $registration];
        }

        usort($indexed, fn (array $a, array $b) => [$a[1]->order, $a[0]] <=> [$b[1]->order, $b[0]]);

        return array_map(fn (array $pair) => $pair[1], $indexed);
    }
}
