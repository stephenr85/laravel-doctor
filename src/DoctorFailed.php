<?php

namespace Rushing\Doctor;

use RuntimeException;

/**
 * Thrown by {@see DoctorRunner} when a GATE audit reports at or above the configured severity floor.
 *
 * The throw lives in the runner, never in an audit. That is the whole point: an audit stays a pure reporter,
 * so ONE audit can serve a doctor command, a conformance sweep, and CI at three different floors. An audit
 * that threw on its own behalf would hardcode one of those three answers for all of them.
 *
 * The exception carries the blocking findings AND the full report, so a caller can act on the failure without
 * re-running anything — including generating corrections from {@see fixable()}.
 */
class DoctorFailed extends RuntimeException
{
    /**
     * @param  list<Finding>  $blocking  the findings that met or exceeded the floor
     */
    public function __construct(
        public readonly array $blocking,
        public readonly DoctorReport $report,
        public readonly DoctorStatus $floor,
    ) {
        parent::__construct(sprintf(
            '%d doctor finding(s) at or above the %s floor: %s',
            count($blocking),
            $floor->value,
            implode(', ', array_map(fn (Finding $f) => $f->check, $blocking)),
        ));
    }

    /**
     * The blocking findings that carry a suggested correction, so a caller can generate the fix rather than
     * re-derive it. Suggestions are passed through exactly as the audit emitted them — the runner never
     * promotes one across a tier boundary, which is enforced structurally by never reading them.
     *
     * @return list<FixableFinding>
     */
    public function fixable(): array
    {
        return array_values(array_filter(
            $this->blocking,
            fn (Finding $finding) => $finding instanceof FixableFinding && $finding->operation !== null,
        ));
    }
}
