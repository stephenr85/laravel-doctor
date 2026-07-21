<?php

namespace Rushing\Doctor;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * The doctor foundation registers no bindings today — it ships pure primitives
 * ({@see Finding}, {@see DoctorStatus}). The provider exists as the package's Laravel
 * entry point and the home for future shared doctor infrastructure (a runner/renderer),
 * kept moat-free (ADR-0095).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
