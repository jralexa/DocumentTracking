<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class DocumentCase extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentCaseFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'case_number',
        'title',
        'owner_type',
        'owner_name',
        'owner_reference',
        'description',
        'status',
        'priority',
        'opened_at',
        'closed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get all documents under this case.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
