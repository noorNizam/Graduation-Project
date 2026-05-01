<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Infrastructure\Repositories\EmailVerificationAttemptRepository;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Application\Services\EmailVerificationService;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Application\Services\UserRegistrationService;
use App\Domain\Services\ServingServiceInterface;
use App\Application\Services\ServingService;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Infrastructure\Repositories\ServingRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(
            EmailVerificationAttemptRepositoryInterface::class,
            EmailVerificationAttemptRepository::class
        );

        // Service bindings
        $this->app->bind(
            EmailVerificationServiceInterface::class,
            EmailVerificationService::class
        );

        $this->app->bind(
            UserRegistrationServiceInterface::class,
            UserRegistrationService::class
        );

        // Serving bindings
        $this->app->bind(
            ServingRepositoryInterface::class,
            ServingRepository::class
        );

        $this->app->bind(
            ServingServiceInterface::class,
            ServingService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
