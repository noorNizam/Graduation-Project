<?php

namespace App\Console\Commands;

use App\Domain\Services\ComplaintServiceInterface;
use Illuminate\Console\Command;

class ResolveExpiredDocumentComplaints extends Command
{
    protected $signature = 'complaints:resolve-expired';

    protected $description = 'Resolve awaiting-documents complaints whose decision deadline has passed';

    public function __construct(
        private ComplaintServiceInterface $complaintService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $complaints = $this->complaintService->getExpiredAwaitingDocuments();
        $ok = 0;
        $fail = 0;

        foreach ($complaints as $complaint) {
            try {
                $result = $this->complaintService->checkDocumentStatusAndApplyDecision($complaint);
                if ($result['success']) {
                    $ok++;
                    $this->info("Decided complaint #{$complaint->id}: {$result['message']}");
                } else {
                    $fail++;
                    $this->error("Could not decide complaint #{$complaint->id}: {$result['message']}");
                }
            } catch (\Exception $e) {
                $fail++;
                $this->error("Failed to decide complaint #{$complaint->id}: {$e->getMessage()}");
            }
        }

        $this->info("Resolved {$ok} expired complaints, {$fail} failed.");

        return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
