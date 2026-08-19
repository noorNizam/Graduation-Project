<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintModel extends Model
{
    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_DOCUMENTS = 'awaiting_documents';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'complaints';

    protected $fillable = [
        'serving_id',
        'serving_request_id',
        'complainant_id',
        'accused_user_id',
        'reason',
        'description',
        'status',
        'admin_note',
        'attachment_path',
        'attachment_name',
        // Columns for the documents flow
        'documents_requested_from',
        'documents_due_at',
        'complainant_documents_uploaded',
        'accused_documents_uploaded',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'documents_due_at' => 'datetime',
        'complainant_documents_uploaded' => 'boolean',
        'accused_documents_uploaded' => 'boolean',
    ];

    protected $appends = ['attachment_url', 'documents'];

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_id');
    }

    public function accusedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accused_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ComplaintDocument::class, 'complaint_id');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAwaitingDocuments(): bool
    {
        return $this->status === self::STATUS_AWAITING_DOCUMENTS;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_DOCUMENTS,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_RESOLVED,
            self::STATUS_REJECTED,
        ]);
    }

    // Documents helpers
    public function areBothDocumentsUploaded(): bool
    {
        return $this->complainant_documents_uploaded && $this->accused_documents_uploaded;
    }

    public function isComplainantOnlyUploaded(): bool
    {
        return $this->complainant_documents_uploaded && ! $this->accused_documents_uploaded;
    }

    public function isAccusedOnlyUploaded(): bool
    {
        return ! $this->complainant_documents_uploaded && $this->accused_documents_uploaded;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->attachment_path) {
            return asset('storage/'.$this->attachment_path);
        }

        return null;
    }

    public function getDocumentsAttribute()
    {
        return $this->getRelationValue('documents');
    }
}
