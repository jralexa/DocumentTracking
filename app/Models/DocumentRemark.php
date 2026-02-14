<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class DocumentRemark extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentRemarkFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'document_transfer_id',
        'document_item_id',
        'parent_remark_id',
        'user_id',
        'context',
        'remark',
        'is_system',
        'remarked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'remarked_at' => 'datetime',
        ];
    }

    /**
     * Get the document this remark belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the transfer this remark belongs to, if any.
     */
    public function documentTransfer(): BelongsTo
    {
        return $this->belongsTo(DocumentTransfer::class);
    }

    /**
     * Get the document item this remark belongs to, if any.
     */
    public function documentItem(): BelongsTo
    {
        return $this->belongsTo(DocumentItem::class);
    }

    /**
     * Get the parent remark for threaded conversations.
     */
    public function parentRemark(): BelongsTo
    {
        return $this->belongsTo(DocumentRemark::class, 'parent_remark_id');
    }

    /**
     * Get child remarks in the thread.
     */
    public function childRemarks(): HasMany
    {
        return $this->hasMany(DocumentRemark::class, 'parent_remark_id');
    }

    /**
     * Get the user who created this remark.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
