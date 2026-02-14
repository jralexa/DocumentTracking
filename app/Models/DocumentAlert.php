<?php

namespace App\Models;

use App\DocumentAlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentAlert extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentAlertFactory> */
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
        'alert_type',
        'severity',
        'message',
        'metadata',
        'is_active',
        'triggered_at',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_type' => DocumentAlertType::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the document associated with the alert.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the department associated with the alert.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user associated with the alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
