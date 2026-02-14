<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class DocumentItem extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'parent_item_id',
        'item_code',
        'name',
        'item_type',
        'status',
        'quantity',
        'sort_order',
        'notes',
    ];

    /**
     * Get the document this item belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the parent item.
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(DocumentItem::class, 'parent_item_id');
    }

    /**
     * Get child items.
     */
    public function childItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class, 'parent_item_id');
    }

    /**
     * Get remarks attached to this document item.
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(DocumentRemark::class, 'document_item_id');
    }
}
