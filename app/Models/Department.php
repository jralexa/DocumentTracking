<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'abbreviation',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all users assigned to the department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all documents currently assigned to the department.
     */
    public function currentDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'current_department_id');
    }

    /**
     * Get pending incoming transfers for this department.
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(DocumentTransfer::class, 'to_department_id');
    }

    /**
     * Get custody records associated with the department.
     */
    public function documentCustodies(): HasMany
    {
        return $this->hasMany(DocumentCustody::class, 'department_id');
    }

    /**
     * Get document alerts associated with the department.
     */
    public function documentAlerts(): HasMany
    {
        return $this->hasMany(DocumentAlert::class, 'department_id');
    }
}
