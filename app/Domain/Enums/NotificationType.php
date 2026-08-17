<?php

namespace App\Domain\Enums;

class NotificationType
{
    public const COMPLAINT_CREATED = 'complaint_created';

    public const COMPLAINT_RESOLVED = 'complaint_resolved';

    public const COMPLAINT_REJECTED = 'complaint_rejected';
    public const DOCUMENTS_REQUESTED = 'documents_requested';
    public const DOCUMENTS_RECEIVED = 'documents_received';

    public const PENALTY_APPLIED = 'penalty_applied';

    public const SERVING_ACCEPTED = 'serving_accepted';

    public const SERVING_REJECTED = 'serving_rejected';

    public const GENERAL = 'general';
}
