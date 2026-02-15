<?php

namespace App\Models;

use App\DocumentVersionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCopy extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentCopyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'document_transfer_id',
        'department_id',
        'user_id',
        'copy_type',
        'storage_location',
        'purpose',
        'recorded_at',
        'is_discarded',
        'discarded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copy_type' => DocumentVersionType::class,
            'recorded_at' => 'datetime',
            'is_discarded' => 'boolean',
            'discarded_at' => 'datetime',
        ];
    }

    /**
     * Get the document this copy belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the transfer associated with this copy record.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(DocumentTransfer::class, 'document_transfer_id');
    }

    /**
     * Get the department that keeps this copy.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user that recorded this copy.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
