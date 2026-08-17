<?php

namespace App\Providers;

use App\Application\Services\ChatService;
use App\Application\Services\ComplaintService;
use App\Application\Services\EmailVerificationService;
use App\Application\Services\IdentityVerificationService;
use App\Application\Services\NotificationService;
use App\Application\Services\PaymentUnitService;
use App\Application\Services\PenaltyService;
use App\Application\Services\SchedulerLogService;
use App\Application\Services\ServingCategoryService;
use App\Application\Services\ServingProposalService;
use App\Application\Services\ServingRequestService;
use App\Application\Services\ServingService;
use App\Application\Services\TopPerformerService;
use App\Application\Services\UserManagementService;
use App\Application\Services\UserRatingService;
use App\Application\Services\UserRegistrationService;
use App\Application\Services\WalletService;
use App\Application\Services\WorkGalleryItemService;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Domain\Repositories\IdentityVerificationRepositoryInterface;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Domain\Repositories\PaymentUnitRepositoryInterface;
use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Domain\Repositories\QuerySynonymRepositoryInterface;
use App\Domain\Repositories\SchedulerLogRepositoryInterface;
use App\Domain\Repositories\ServingCategoryRepositoryInterface;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\TopPerformerRepositoryInterface;
use App\Domain\Repositories\UserRatingRepositoryInterface;
use App\Domain\Repositories\UserSearchHistoryRepositoryInterface;
use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Repositories\WorkGalleryItemFileRepositoryInterface;
use App\Domain\Repositories\WorkGalleryItemRepositoryInterface;
use App\Domain\Services\ChatServiceInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\IdentityVerificationServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\PaymentUnitServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Domain\Services\SchedulerLogServiceInterface;
use App\Domain\Services\ServingCategoryServiceInterface;
use App\Domain\Services\ServingProposalServiceInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Domain\Services\ServingServiceInterface;
use App\Domain\Services\TopPerformerServiceInterface;
use App\Domain\Services\UserManagementServiceInterface;
use App\Domain\Services\UserRatingServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Domain\Services\WalletServiceInterface;
use App\Domain\Services\WorkGalleryItemServiceInterface;
use App\Infrastructure\Repositories\ChatRepository;
use App\Infrastructure\Repositories\ComplaintRepository;
use App\Infrastructure\Repositories\EmailVerificationAttemptRepository;
use App\Infrastructure\Repositories\IdentityVerificationRepository;
use App\Infrastructure\Repositories\NotificationRepository;
use App\Infrastructure\Repositories\PaymentUnitRepository;
use App\Infrastructure\Repositories\PenaltyRepository;
use App\Infrastructure\Repositories\QuerySynonymRepository;
use App\Infrastructure\Repositories\SchedulerLogRepository;
use App\Infrastructure\Repositories\ServingCategoryRepository;
use App\Infrastructure\Repositories\ServingRepository;
use App\Infrastructure\Repositories\ServingRequestRepository;
use App\Infrastructure\Repositories\TopPerformerRepository;
use App\Infrastructure\Repositories\UserRatingRepository;
use App\Infrastructure\Repositories\UserSearchHistoryRepository;
use App\Infrastructure\Repositories\WalletRepository;
use App\Infrastructure\Repositories\WorkGalleryItemFileRepository;
use App\Infrastructure\Repositories\WorkGalleryItemRepository;
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

        // Serving Proposal bindings
        $this->app->bind(
            ServingProposalServiceInterface::class,
            ServingProposalService::class
        );

        // Top Performer bindings
        $this->app->bind(
            TopPerformerRepositoryInterface::class,
            TopPerformerRepository::class
        );

        $this->app->bind(
            TopPerformerServiceInterface::class,
            TopPerformerService::class
        );

        // Scheduler Log bindings
        $this->app->bind(
            SchedulerLogRepositoryInterface::class,
            SchedulerLogRepository::class
        );

        $this->app->bind(
            SchedulerLogServiceInterface::class,
            SchedulerLogService::class
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

        // User Rating bindings
        $this->app->bind(
            UserRatingRepositoryInterface::class,
            UserRatingRepository::class
        );

        $this->app->bind(
            UserRatingServiceInterface::class,
            UserRatingService::class
        );

        // User Search History bindings
        $this->app->bind(
            UserSearchHistoryRepositoryInterface::class,
            UserSearchHistoryRepository::class
        );

        // Query Synonym bindings
        $this->app->bind(
            QuerySynonymRepositoryInterface::class,
            QuerySynonymRepository::class
        );

        // Complaint & Penalty bindings
        $this->app->bind(
            ComplaintRepositoryInterface::class,
            ComplaintRepository::class
        );

        $this->app->bind(
            ComplaintServiceInterface::class,
            ComplaintService::class
        );

        $this->app->bind(
            PenaltyRepositoryInterface::class,
            PenaltyRepository::class
        );

        $this->app->bind(
            PenaltyServiceInterface::class,
            PenaltyService::class
        );
        $this->app->bind(
            NotificationRepositoryInterface::class,
            NotificationRepository::class
        );

        $this->app->bind(
            NotificationServiceInterface::class,
            NotificationService::class
        );

        // Chat bindings
        $this->app->bind(
            ChatRepositoryInterface::class,
            ChatRepository::class
        );

        $this->app->bind(
            ChatServiceInterface::class,
            ChatService::class
        );

        // Work Gallery Item bindings
        $this->app->bind(
            WorkGalleryItemRepositoryInterface::class,
            WorkGalleryItemRepository::class
        );

        $this->app->bind(
            WorkGalleryItemFileRepositoryInterface::class,
            WorkGalleryItemFileRepository::class
        );

        $this->app->bind(
            WorkGalleryItemServiceInterface::class,
            WorkGalleryItemService::class
        );
        // Authentication
        $this->app->bind(
            IdentityVerificationRepositoryInterface::class,
            IdentityVerificationRepository::class
        );

        $this->app->bind(
            IdentityVerificationServiceInterface::class,
            IdentityVerificationService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
