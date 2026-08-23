<?php

use Illuminate\Console\Command;
use Rushing\Doctor\Concerns\RunsDoctorFloor;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRunner;

/**
 * beam-facade ticket 72 — the half of the fix that is not the runner's.
 *
 * The runner's contract is that it THROWS above the floor and {@see RunsDoctorFloor::runAtFloor()} converts
 * that back into data. An error finding that reached the report but not `$blocking` would render red and
 * exit 0 — a broken gate audit reported as a green command, which is the failure mode the whole ticket
 * exists to close. So this stands the path a real `*:doctor` command takes, end to end.
 */
class ExitCodeThrowingAudit implements DoctorAudit
{
    public function run(): array
    {
        throw new RuntimeException('the precondition this audit needed is absent');
    }
}

/** The shape every `*:doctor` command in the fleet has: parse the floor, run at it, render, exit on the gate. */
class FixtureDoctorCommand extends Command
{
    use RunsDoctorFloor;

    protected $signature = 'fixture:doctor {--floor=fail} {--gate=1}';

    public function handle(DoctorRunner $runner): int
    {
        $floor = $this->parseFloor();

        if ($floor === null) {
            return self::FAILURE;
        }

        [$report, $blocked] = $this->runAtFloor($runner, [
            new DoctorRegistration('fixture', ExitCodeThrowingAudit::class, gate: (bool) $this->option('gate')),
        ], $floor);

        $this->renderFindings($report->findings);

        return $blocked ? self::FAILURE : self::SUCCESS;
    }
}

beforeEach(function () {
    $this->app[Illuminate\Contracts\Console\Kernel::class]->registerCommand(new FixtureDoctorCommand);
});

it('reddens a doctor command exit code when a GATE audit could not run', function () {
    $this->artisan('fixture:doctor')
        ->expectsOutputToContain('unverified is not passed')
        ->assertExitCode(Command::FAILURE);
});

it('leaves the command green when the same broken audit is advisory', function () {
    // The command still SAYS so — a Warn line, never silently — it just does not block.
    $this->artisan('fixture:doctor', ['--gate' => '0'])
        ->expectsOutputToContain('contributed no findings, which is not the same as finding nothing')
        ->assertExitCode(Command::SUCCESS);
});
