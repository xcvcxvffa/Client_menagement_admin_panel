<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class Business extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::created(function ($business) {
            // Because spatie caches the team_id, we should set it explicitly here just in case.
            $previousTeamId = getPermissionsTeamId();
            setPermissionsTeamId($business->id);

            // Create default 'Admin' role for this business
            $adminRole = Role::firstOrCreate([
                'name' => 'Admin',
                'business_id' => $business->id,
                'guard_name' => 'web'
            ]);

            // Assign all existing permissions to this Admin role
            $adminRole->syncPermissions(Permission::all());

            setPermissionsTeamId($previousTeamId);
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'currency',
        'branding_color',
        'logo',
        'address',
        'tax_number',
    ];

    /**
     * Users associated with this business.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Team members pivot details.
     */
    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Clients belonging to this business.
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Leads belonging to this business.
     */
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Projects belonging to this business.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Quotes belonging to this business.
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Invoices belonging to this business.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }



    /**
     * Activity logs recorded for this business.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
