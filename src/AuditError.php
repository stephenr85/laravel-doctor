<?php

namespace Rushing\Doctor;

use Throwable;

/**
 * The {@see Finding} an audit that could not report becomes — the one shape every runner in the fleet
 * turns a broken audit into.
 *
 * An audit is a diagnostic that runs against whatever state a root happens to be in, so *an audit meeting
 * an unmet precondition is a normal event*: a query against a column a lagging database has not got, a file
 * the root never created. The roots whose state is worst are the roots most likely to carry a throwing
 * audit, and they are exactly the roots whose report is worth reading — so a runner catches, reports, and
 * carries on rather than dying with every finding it had already collected.
 *
 * Two rulings, inherited from beam-facade ticket 56 and re-stated here because this is now where they are
 * rendered:
 *
 *  - **Never silently.** A suppressed exception with no finding is strictly worse than the crash, because
 *    the report then goes green *because* an audit failed.
 *  - **Warn advisory, Fail on a gate.** An audit that could not run has not found anything, so it must not
 *    redden an advisory registration on the strength of not knowing — but a GATE registration's contract is
 *    that its subject was *verified*, and a gate that degrades to "no findings" is how a gate stops gating.
 *    Unverified is not passed. The severity is therefore read off the REGISTRATION's gate flag, never off
 *    whatever threw: the same audit is a gate at one host and advisory at another, and an audit that threw
 *    cannot be consulted about how bad its own absence is.
 *
 * **Why this lives in doctor rather than in each runner (ticket 72).** Two runners produce it —
 * {@see DoctorRunner} (behind every `*:doctor` command) and surgeon's conformance sweep — and each keeps its
 * own catch, because their loops differ (one throws above a floor, the other returns fixable pairs). What
 * they must NOT keep their own copy of is the *finding*: the check-string is an operator-facing vocabulary
 * that has to mean the same thing whichever tool printed it, and the detail's closing sentence is the ruling
 * above rendered at the point of reading. Two copies of a ruling drift, and the drifted one still reads as
 * authoritative.
 */
class AuditError
{
    /** The check-string an audit that threw while running reports under. */
    public const CHECK = 'audit-errored';

    /** The resolution phase's variant — the same event one phase earlier, told apart without reading code. */
    public const CHECK_RESOLVE = self::CHECK.'.resolve';

    /** How much of an exception message survives into a detail — a QueryException carries whole SQL. */
    private const MESSAGE_LIMIT = 300;

    /**
     * A registration naming a class that no longer exists, or whose constructor throws, fails before the
     * audit runs at all. Distinguished by check-string and verb so an operator can tell "this audit is gone"
     * (stale wiring) from "this audit could not look" (stale state).
     *
     * @param  class-string  $audit  the registered class-string, named in the finding
     */
    public static function resolving(string $audit, bool $gate, Throwable $e): Finding
    {
        return self::finding(self::CHECK_RESOLVE, $audit.' could not be resolved', $gate, $e);
    }

    /**
     * @param  class-string  $audit  the registered class-string, named in the finding
     */
    public static function running(string $audit, bool $gate, Throwable $e): Finding
    {
        return self::finding(self::CHECK, $audit.' threw while running', $gate, $e);
    }

    private static function finding(string $check, string $what, bool $gate, Throwable $e): Finding
    {
        $detail = $what
            .' — '.$e::class.': '.self::firstLine($e->getMessage())
            .' ('.basename($e->getFile()).':'.$e->getLine().'). '
            .($gate
                ? 'A GATE audit that could not run has not verified its subject, so it reports Fail: unverified is not passed.'
                : 'The rest of the run completed; this audit contributed no findings, which is not the same as finding nothing.');

        // BOTH arms measured nothing — that is what an audit that could not run IS, and the prose above
        // has said so since before the flag existed ("contributed no findings, which is not the same as
        // finding nothing"). Conclusiveness is orthogonal to severity (the reason 124 put it off the enum),
        // so the gate arm stays a Fail AND carries the flag: "unverified is not passed" is the severity
        // ruling; "it did not measure" is the separate fact. Retro-flagged by api-surface-coherence 128 —
        // leaving it out made DoctorReport::inconclusive() omit the estate's most important inconclusive
        // event, which is a lie by omission in the very population the flag was built to report. Status is
        // unchanged either way, so no floor and no exit code moves.
        return new Finding(
            $gate ? DoctorStatus::Fail : DoctorStatus::Warn,
            $check,
            $detail,
            conclusive: false,
        );
    }

    /** One line, bounded — a QueryException's message carries whole SQL and would swamp the report. */
    private static function firstLine(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return '(no message)'; // an Error thrown bare still has to name something readable
        }

        $line = trim((string) strtok($message, "\r\n"));

        return strlen($line) > self::MESSAGE_LIMIT
            ? substr($line, 0, self::MESSAGE_LIMIT).'…'
            : $line;
    }
}
