<?php

namespace App\Models;

use App\DocumentVersionType;
use App\TransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentTransferFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'from_department_id',
        'to_department_id',
        'forwarded_by_user_id',
        'accepted_by_user_id',
        'status',
        'remarks',
        'forward_version_type',
        'copy_kept',
        'copy_storage_location',
        'copy_purpose',
        'dispatch_method',
        'dispatch_reference',
        'release_receipt_reference',
        'forwarded_at',
        'accepted_at',
        'recalled_at',
        'recalled_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'forward_version_type' => DocumentVersionType::class,
            'copy_kept' => 'boolean',
            'forwarded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'recalled_at' => 'datetime',
        ];
    }

    /**
     * Get the document for this transfer.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the originating department.
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * Get the destination department.
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * Get the user who forwarded this transfer.
     */
    public function forwardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by_user_id');
    }

    /**
     * Get the user who accepted this transfer.
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * Get the user who recalled this transfer.
     */
    public function recalledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recalled_by_user_id');
    }

    /**
     * Get remarks attached to this transfer.
     */
    public function remarksThread(): HasMany
    {
        return $this->hasMany(DocumentRemark::class, 'document_transfer_id');
    }

    /**
     * Get copy records associated with this transfer.
     */
    public function copies(): HasMany
    {
        return $this->hasMany(DocumentCopy::class, 'document_transfer_id');
    }
}
