<?php

namespace App\Providers;

use App\Application\Services\EmailVerificationService;
use App\Application\Services\ServingRequestService;
use App\Application\Services\ServingService;
use App\Application\Services\UserRegistrationService;
use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Domain\Services\ServingServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Infrastructure\Repositories\EmailVerificationAttemptRepository;
use App\Infrastructure\Repositories\ServingRepository;
use App\Infrastructure\Repositories\ServingRequestRepository;
use Illuminate\Support\ServiceProvider;

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

        $this->app->bind(
            ServingRequestRepositoryInterface::class,
            ServingRequestRepository::class
        );

        $this->app->bind(
            ServingRequestServiceInterface::class,
            ServingRequestService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
