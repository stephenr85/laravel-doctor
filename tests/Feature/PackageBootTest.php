<?php

use Rushing\Doctor\ServiceProvider;

it('boots the package provider without registering the moat', function () {
    expect(app()->getProviders(ServiceProvider::class))->not->toBeEmpty();
});
