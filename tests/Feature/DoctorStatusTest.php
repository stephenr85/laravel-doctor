<?php

use Rushing\Doctor\DoctorStatus;

it('carries the pass/warn/fail vocabulary as a backed enum', function () {
    expect(DoctorStatus::Pass->value)->toBe('pass');
    expect(DoctorStatus::Warn->value)->toBe('warn');
    expect(DoctorStatus::Fail->value)->toBe('fail');
    expect(DoctorStatus::from('warn'))->toBe(DoctorStatus::Warn);
});
