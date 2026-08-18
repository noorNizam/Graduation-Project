<?php

namespace App\Domain\Services;

interface ServingProposalServiceInterface
{
    public function rebuildIndex(): array;

    public function getProposedServings(int $userId, int $skip, int $take): array;

    public function indexStatus(): array;
}
