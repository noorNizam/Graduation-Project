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
use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Infrastructure\Repositories\ComplaintRepository;
use App\Application\Services\ComplaintService;
use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Infrastructure\Repositories\PenaltyRepository;
use App\Domain\Services\PenaltyServiceInterface;
use App\Application\Services\PenaltyService;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\UserRepository;
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

        // Complaint bindings (شغلك)
        $this->app->bind(
            ComplaintRepositoryInterface::class,
            ComplaintRepository::class
        );

        $this->app->singleton(ComplaintService::class, function ($app) {
            return new ComplaintService(
                $app->make(ComplaintRepositoryInterface::class)
            );
        });

        // Penalty bindings (شغلك)
        $this->app->bind(
            PenaltyRepositoryInterface::class,
            PenaltyRepository::class
        );

        $this->app->bind(
            PenaltyServiceInterface::class,
            PenaltyService::class
        );

        $this->app->singleton(PenaltyService::class, function ($app) {
            return new PenaltyService(
                $app->make(PenaltyRepositoryInterface::class),
                $app->make(UserRepositoryInterface::class)
            );
        });

        // User Repository (شغلك)
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}