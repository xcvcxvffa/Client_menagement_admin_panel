<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'monthly_salary',
        'notes',
    ];

    /**
     * Get the business this team member belongs to.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Projects associated with the team member.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_team_member');
    }
}
