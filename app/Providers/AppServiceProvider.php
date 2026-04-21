<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Infrastructure\Repositories\EmailVerificationAttemptRepository;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Application\Services\EmailVerificationService;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Application\Services\UserRegistrationService;

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
    }

    public function boot(): void
    {
        //
    }
}
