<?php

namespace App\Models;

use App\DocumentEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentEvent extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentEventFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'document_transfer_id',
        'document_custody_id',
        'document_relationship_id',
        'acted_by_user_id',
        'event_type',
        'context',
        'message',
        'payload',
        'occurred_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => DocumentEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the document associated with the event.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the transfer associated with this event, if any.
     */
    public function documentTransfer(): BelongsTo
    {
        return $this->belongsTo(DocumentTransfer::class);
    }

    /**
     * Get the custody record associated with this event, if any.
     */
    public function documentCustody(): BelongsTo
    {
        return $this->belongsTo(DocumentCustody::class);
    }

    /**
     * Get the relationship record associated with this event, if any.
     */
    public function documentRelationship(): BelongsTo
    {
        return $this->belongsTo(DocumentRelationship::class);
    }

    /**
     * Get the user who triggered the event, if any.
     */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }
}
