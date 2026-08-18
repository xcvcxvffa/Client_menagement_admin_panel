<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'taskable_type',
        'taskable_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'assigned_to',
        'sort_order',
        'hours_spent',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
        'hours_spent' => 'decimal:2',
    ];

    /**
     * Get the parent taskable model (Project, Retainer, Content, etc.).
     */
    public function taskable()
    {
        return $this->morphTo();
    }

    /**
     * Get the project that owns the task (Legacy or direct relation).
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user this task is assigned to.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the comments for the task.
     */
    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }
}
