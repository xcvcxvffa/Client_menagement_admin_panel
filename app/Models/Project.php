<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, BelongsToBusiness, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'business_id',
        'client_id',
        'name',
        'description',
        'status',
        'budget',
        'started_at',
        'due_at',
        'domain_name',
        'domain_registrar',
        'domain_cost',
        'domain_purchased_at',
        'domain_expires_at',
        'domain_auto_renew',
        'hosting_provider',
        'hosting_cost',
        'hosting_purchased_at',
        'hosting_expires_at',
        'hosting_auto_renew',
        'domain_hosting_notes',
        'team_visible_to_client',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'started_at' => 'date',
        'due_at' => 'date',
        'domain_cost' => 'decimal:2',
        'domain_purchased_at' => 'date',
        'domain_expires_at' => 'date',
        'domain_auto_renew' => 'boolean',
        'hosting_cost' => 'decimal:2',
        'hosting_purchased_at' => 'date',
        'hosting_expires_at' => 'date',
        'hosting_auto_renew' => 'boolean',
    ];

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    public function retainers()
    {
        return $this->hasMany(Retainer::class);
    }


    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the client that owns the project.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Team members associated with the project.
     */
    public function teamMembers()
    {
        return $this->belongsToMany(TeamMember::class, 'project_team_member');
    }

    /**
     * Tasks associated with the project.
     */
    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /**
     * Updates (progress timeline) associated with the project.
     */
    public function updates()
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    /**
     * Approvals associated with the project.
     */
    public function approvals()
    {
        return $this->hasMany(ProjectApproval::class);
    }

    /**
     * Get the payments for this project through invoices.
     */
    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    /**
     * Calculate the progress percentage based on completed tasks.
     */
    public function getProgressPercentageAttribute(): int
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->tasks()->where('status', 'done')->count();
        return (int) round(($completed / $total) * 100);
    }
}
