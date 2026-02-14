<?php

namespace App\Models;

use App\DocumentVersionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentCustody extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentCustodyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'department_id',
        'user_id',
        'version_type',
        'is_current',
        'status',
        'physical_location',
        'storage_reference',
        'purpose',
        'received_at',
        'released_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_type' => DocumentVersionType::class,
            'is_current' => 'boolean',
            'received_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Get the document this custody record belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the department associated with this custody record.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user custodian associated with this record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to current custody records.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope a query to original custody records.
     */
    public function scopeOriginal(Builder $query): Builder
    {
        return $query->where('version_type', DocumentVersionType::Original->value);
    }
}
