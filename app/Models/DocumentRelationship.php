<?php

namespace App\Models;

use App\DocumentRelationshipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentRelationship extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentRelationshipFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source_document_id',
        'related_document_id',
        'relation_type',
        'created_by_user_id',
        'notes',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation_type' => DocumentRelationshipType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Get the source document.
     */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    /**
     * Get the related document.
     */
    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'related_document_id');
    }

    /**
     * Get the user who created this relationship.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
