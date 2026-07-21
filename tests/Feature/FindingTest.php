<?php

use Schemastud\Doctor\DoctorStatus;
use Schemastud\Doctor\Finding;

it('builds a finding via the status factories', function () {
    $pass = Finding::pass('db', 'connected');
    expect($pass->status)->toBe(DoctorStatus::Pass);
    expect($pass->check)->toBe('db');
    expect($pass->detail)->toBe('connected');

    expect(Finding::warn('cache', 'stale')->status)->toBe(DoctorStatus::Warn);
    expect(Finding::fail('queue', 'down')->status)->toBe(DoctorStatus::Fail);
});

it('is constructable directly with an explicit status', function () {
    $f = new Finding(DoctorStatus::Fail, 'ext', 'missing pgvector');
    expect($f->status)->toBe(DoctorStatus::Fail);
    expect($f->check)->toBe('ext');
});
