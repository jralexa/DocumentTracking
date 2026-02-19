<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'category',
        'action',
        'message',
        'user_id',
        'ip_address',
        'http_method',
        'route_name',
        'request_path',
        'entity_type',
        'entity_id',
        'context',
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
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the actor user tied to the log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
