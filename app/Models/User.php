<?php

namespace App\Models;

use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the department assigned to the user.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get all documents currently assigned to the user.
     */
    public function currentDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'current_user_id');
    }

    /**
     * Get all transfers forwarded by the user.
     */
    public function forwardedTransfers(): HasMany
    {
        return $this->hasMany(DocumentTransfer::class, 'forwarded_by_user_id');
    }

    /**
     * Get all transfers accepted by the user.
     */
    public function acceptedTransfers(): HasMany
    {
        return $this->hasMany(DocumentTransfer::class, 'accepted_by_user_id');
    }

    /**
     * Get custody records assigned to the user.
     */
    public function documentCustodies(): HasMany
    {
        return $this->hasMany(DocumentCustody::class, 'user_id');
    }

    /**
     * Get document relationships created by the user.
     */
    public function createdDocumentRelationships(): HasMany
    {
        return $this->hasMany(DocumentRelationship::class, 'created_by_user_id');
    }

    /**
     * Get document events triggered by the user.
     */
    public function documentEvents(): HasMany
    {
        return $this->hasMany(DocumentEvent::class, 'acted_by_user_id');
    }

    /**
     * Get document remarks authored by the user.
     */
    public function documentRemarks(): HasMany
    {
        return $this->hasMany(DocumentRemark::class);
    }

    /**
     * Get document alerts associated with the user.
     */
    public function documentAlerts(): HasMany
    {
        return $this->hasMany(DocumentAlert::class);
    }

    /**
     * Determine if the user has the given role.
     */
    public function hasRole(UserRole|string $role): bool
    {
        if (! $this->role instanceof UserRole) {
            return false;
        }

        $roleValue = $role instanceof UserRole ? $role->value : strtolower($role);

        return $this->role->value === $roleValue;
    }

    /**
     * Determine if the user has one of the given roles.
     *
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        if (! $this->role instanceof UserRole) {
            return false;
        }

        $allowedRoles = array_map(
            static fn (UserRole|string $role): string => $role instanceof UserRole ? $role->value : strtolower($role),
            $roles
        );

        return in_array($this->role->value, $allowedRoles, true);
    }

    /**
     * Determine if the user can view document lists.
     */
    public function canViewDocuments(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin,
            UserRole::Manager,
            UserRole::Regular,
        ]);
    }

    /**
     * Determine if the user can process documents in workflow queues.
     */
    public function canProcessDocuments(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin,
            UserRole::Manager,
            UserRole::Regular,
        ]);
    }

    /**
     * Determine if the user can manage documents.
     */
    public function canManageDocuments(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin,
            UserRole::Manager,
        ]);
    }

    /**
     * Determine if the user can export reports.
     */
    public function canExportReports(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin,
            UserRole::Manager,
        ]);
    }
}
