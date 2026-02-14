<?php

namespace App\Models;

use App\DocumentRelationshipType;
use App\DocumentWorkflowStatus;
use App\DocumentVersionType;
use App\TransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_case_id',
        'current_department_id',
        'current_user_id',
        'tracking_number',
        'reference_number',
        'subject',
        'document_type',
        'owner_type',
        'owner_name',
        'status',
        'priority',
        'received_at',
        'due_at',
        'completed_at',
        'metadata',
        'is_returnable',
        'return_deadline',
        'returned_at',
        'returned_to',
        'original_current_department_id',
        'original_custodian_user_id',
        'original_physical_location',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentWorkflowStatus::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
            'is_returnable' => 'boolean',
            'return_deadline' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Get the case this document belongs to.
     */
    public function documentCase(): BelongsTo
    {
        return $this->belongsTo(DocumentCase::class);
    }

    /**
     * Get the current department handling the document.
     */
    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    /**
     * Get the current user handling the document.
     */
    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    /**
     * Get the current department holding the original document.
     */
    public function originalCurrentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'original_current_department_id');
    }

    /**
     * Get the current user custodian of the original document.
     */
    public function originalCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_custodian_user_id');
    }

    /**
     * Get all items under this document.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    /**
     * Get root-level items under this document.
     */
    public function rootItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->whereNull('parent_item_id');
    }

    /**
     * Get all transfers for this document.
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(DocumentTransfer::class);
    }

    /**
     * Get the latest transfer for this document.
     */
    public function latestTransfer(): HasOne
    {
        return $this->hasOne(DocumentTransfer::class)->latestOfMany();
    }

    /**
     * Get all custody records for this document.
     */
    public function custodies(): HasMany
    {
        return $this->hasMany(DocumentCustody::class);
    }

    /**
     * Get all audit events for this document.
     */
    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class);
    }

    /**
     * Get all remarks for this document.
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(DocumentRemark::class);
    }

    /**
     * Get alert records for this document.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(DocumentAlert::class);
    }

    /**
     * Get the currently active original custody record.
     */
    public function currentOriginalCustody(): HasOne
    {
        return $this->hasOne(DocumentCustody::class)
            ->where('version_type', DocumentVersionType::Original->value)
            ->where('is_current', true);
    }

    /**
     * Get relationships where this document is the source.
     */
    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(DocumentRelationship::class, 'source_document_id');
    }

    /**
     * Get relationships where this document is the related target.
     */
    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(DocumentRelationship::class, 'related_document_id');
    }

    /**
     * Get merge relationships where this document was merged into another.
     */
    public function mergedIntoRelationships(): HasMany
    {
        return $this->outgoingRelationships()->where('relation_type', DocumentRelationshipType::MergedInto->value);
    }

    /**
     * Get split relationships where this document was split from another.
     */
    public function splitFromRelationships(): HasMany
    {
        return $this->outgoingRelationships()->where('relation_type', DocumentRelationshipType::SplitFrom->value);
    }

    /**
     * Scope a query to incoming queue records for the given user.
     */
    public function scopeForIncomingQueue(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('latestTransfer', function ($transferQuery) use ($user) {
                $transferQuery
                    ->where('to_department_id', $user->department_id)
                    ->where('status', TransferStatus::Pending->value)
                    ->whereNull('accepted_at');
            })
            ->where('status', DocumentWorkflowStatus::Outgoing->value);
    }

    /**
     * Scope a query to on-queue records for the given user.
     */
    public function scopeForOnQueue(Builder $query, User $user): Builder
    {
        return $query
            ->where('current_user_id', $user->id)
            ->where('current_department_id', $user->department_id)
            ->where('status', DocumentWorkflowStatus::OnQueue->value);
    }

    /**
     * Scope a query to outgoing queue records for the given user.
     */
    public function scopeForOutgoing(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('latestTransfer', function ($transferQuery) use ($user) {
                $transferQuery
                    ->where('forwarded_by_user_id', $user->id)
                    ->where('status', TransferStatus::Pending->value)
                    ->whereNull('accepted_at');
            })
            ->where('status', DocumentWorkflowStatus::Outgoing->value);
    }
}
