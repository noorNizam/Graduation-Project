<?php

namespace App\Events;

use App\Infrastructure\Models\ComplaintModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ComplaintModel $complaint,
        public string $adminNote
    ) {}
}
