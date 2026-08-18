<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'current_business_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

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
        ];
    }

    /**
     * The businesses the user belongs to.
     */
    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    public function contents()
    {
        return $this->hasMany(Content::class, 'assigned_to');
    }

    /**
     * The user's currently active business context.
     */
    public function currentBusiness()
    {
        return $this->belongsTo(Business::class, 'current_business_id');
    }

    /**
     * Determine if this user is the Owner of a given business.
     * Falls back to current_business_id when no $businessId is provided.
     * Uses Spatie's team-scoped role check so multi-tenancy is respected.
     */
    public function isOwnerOfBusiness(?int $businessId = null): bool
    {
        $bizId = $businessId ?? $this->current_business_id;
        if (!$bizId) {
            return false;
        }
        setPermissionsTeamId($bizId);
        return $this->hasRole('Owner');
    }

}
