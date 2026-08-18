<?php

namespace App\Domain\Enums;

class NotificationType
{
    // ===================== Complaints =====================
    public const COMPLAINT_CREATED = 'complaint_created';

    public const COMPLAINT_RESOLVED = 'complaint_resolved';

    public const COMPLAINT_REJECTED = 'complaint_rejected';

    public const COMPLAINT_STATUS_CHANGED = 'complaint_status_changed';

    public const NEW_COMPLAINT = 'new_complaint';

    // ===================== Penalties =====================
    public const PENALTY_APPLIED = 'penalty_applied';

    // ===================== Servings =====================
    public const SERVING_REQUEST = 'serving_request';

    public const SERVING_ACCEPTED = 'serving_accepted';

    public const SERVING_REJECTED = 'serving_rejected';

    public const REQUEST_DELETED = 'request_deleted';

    public const COMPLETION_CONFIRMED = 'completion_confirmed';

    public const REVISION_REQUESTED = 'revision_requested';

    public const DISPUTE_OPENED = 'dispute_opened';

    public const DISPUTE_RESOLVED = 'dispute_resolved';

    public const NEW_VOLUNTARY_SERVING = 'new_voluntary_serving';

    // ===================== Documents =====================
    public const DOCUMENTS_REQUESTED = 'documents_requested';

    public const DOCUMENTS_UPLOADED = 'documents_uploaded';

    // ===================== General =====================
    public const GENERAL = 'general';
}
