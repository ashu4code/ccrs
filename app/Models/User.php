<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An official of the Forum — a Relationship Manager or a Competent Authority.
 * External users (consumers) live in the `consumers` table.
 */
#[Fillable(['name', 'display_name', 'username', 'email', 'password', 'role', 'region', 'discom_id', 'circle_id', 'division_id', 'subdivision_id', 'section_id', 'mobile', 'address', 'designation'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * Determine whether the official holds the given role.
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Complaints assigned to this official for relationship-manager review.
     */
    public function managedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'regional_manager_id');
    }

    /**
     * Complaints escalated to this official as the competent authority.
     */
    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'competent_authority_id');
    }

    public function discom(): BelongsTo
    {
        return $this->belongsTo(Discom::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
