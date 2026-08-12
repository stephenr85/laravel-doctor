<?php

namespace Rushing\Doctor;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * The doctor foundation ships the pure primitives ({@see Finding}, {@see DoctorStatus}) plus the shared
 * infrastructure this provider was always reserved for: a {@see DoctorRunner} that executes registered audits
 * and throws above a configured severity floor, and a {@see DoctorRenderer} for consistent output. Every
 * doctor command in the fleet previously reimplemented that loop. Kept moat-free (ADR-0095).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DoctorRunner::class, fn ($app) => new DoctorRunner($app));
    }

    public function boot(): void
    {
        //
    }
}
