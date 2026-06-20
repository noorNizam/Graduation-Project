<?php

namespace App\Providers;

use App\Application\Services\ComplaintService;
use App\Application\Services\EmailVerificationService;
use App\Application\Services\PaymentUnitService;
use App\Application\Services\PenaltyService;
use App\Application\Services\ServingCategoryService;
use App\Application\Services\ServingRequestService;
use App\Application\Services\ServingService;
use App\Application\Services\UserManagementService;
use App\Application\Services\UserRegistrationService;
use App\Application\Services\WalletService;
use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Domain\Repositories\PaymentUnitRepositoryInterface;
use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Domain\Repositories\ServingCategoryRepositoryInterface;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\PaymentUnitServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Domain\Services\ServingCategoryServiceInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Domain\Services\ServingServiceInterface;
use App\Domain\Services\UserManagementServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Domain\Services\WalletServiceInterface;
use App\Infrastructure\Repositories\ComplaintRepository;
use App\Infrastructure\Repositories\EmailVerificationAttemptRepository;
use App\Infrastructure\Repositories\PaymentUnitRepository;
use App\Infrastructure\Repositories\PenaltyRepository;
use App\Infrastructure\Repositories\ServingCategoryRepository;
use App\Infrastructure\Repositories\ServingRepository;
use App\Infrastructure\Repositories\ServingRequestRepository;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Repositories\WalletRepository;
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

        // Payment Unit bindings
        $this->app->bind(
            PaymentUnitRepositoryInterface::class,
            PaymentUnitRepository::class
        );

        $this->app->bind(
            PaymentUnitServiceInterface::class,
            PaymentUnitService::class
        );

        // User Management bindings
        $this->app->bind(
            UserManagementServiceInterface::class,
            UserManagementService::class
        );

        // Serving Category bindings
        $this->app->bind(
            ServingCategoryRepositoryInterface::class,
            ServingCategoryRepository::class
        );

        $this->app->bind(
            ServingCategoryServiceInterface::class,
            ServingCategoryService::class
        );

        // Wallet bindings
        $this->app->bind(
            WalletRepositoryInterface::class,
            WalletRepository::class
        );

        $this->app->bind(
            WalletServiceInterface::class,
            WalletService::class
        );

        // Complaint bindings
        $this->app->bind(
            ComplaintRepositoryInterface::class,
            ComplaintRepository::class
        );

        $this->app->singleton(ComplaintService::class, function ($app) {
            return new ComplaintService(
                $app->make(ComplaintRepositoryInterface::class)
            );
        });

        // Penalty bindings
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

        // User Repository
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
