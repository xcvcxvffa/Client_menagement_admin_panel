<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Content extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'business_id',
        'client_id',
        'project_id',
        'retainer_id',
        'content_type_id',
        'platform_id',
        'title',
        'description',
        'brief',
        'caption',
        'publish_date',
        'due_date',
        'priority',
        'status',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'due_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($content) {
            // 1. Project and Retainer mutual exclusion
            if ($content->project_id && $content->retainer_id) {
                throw new \Exception('A content item cannot belong to both a Project and a Retainer simultaneously.');
            }

            // 2. Cross-business Client check
            if ($content->client_id) {
                $client = Client::withoutGlobalScope('business')->find($content->client_id);
                if ($client && $client->business_id !== $content->business_id) {
                    throw new \Exception('Content client must belong to the current business.');
                }
            }

            // 3. Project Client & Business check
            if ($content->project_id) {
                $project = Project::withoutGlobalScope('business')->find($content->project_id);
                if ($project && $project->client_id !== $content->client_id) {
                    throw new \Exception('Content project must belong to the selected client.');
                }
                if ($project && $project->business_id !== $content->business_id) {
                    throw new \Exception('Content project must belong to the current business.');
                }
            }

            // 4. Retainer Client & Business check
            if ($content->retainer_id) {
                $retainer = Retainer::withoutGlobalScope('business')->find($content->retainer_id);
                if ($retainer && $retainer->client_id !== $content->client_id) {
                    throw new \Exception('Content retainer must belong to the selected client.');
                }
                if ($retainer && $retainer->business_id !== $content->business_id) {
                    throw new \Exception('Content retainer must belong to the current business.');
                }
            }
            // 5. ContentType & Platform Business check
            if ($content->content_type_id) {
                $type = ContentType::find($content->content_type_id);
                if ($type && $type->business_id !== null && $type->business_id !== $content->business_id) {
                    throw new \Exception('Content Type belongs to another business.');
                }
            }
            if ($content->platform_id) {
                $platform = Platform::find($content->platform_id);
                if ($platform && $platform->business_id !== null && $platform->business_id !== $content->business_id) {
                    throw new \Exception('Platform belongs to another business.');
                }
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function retainer(): BelongsTo
    {
        return $this->belongsTo(Retainer::class);
    }

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }
    
    public function approvals(): HasMany
    {
        return $this->hasMany(ContentApproval::class);
    }
    
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class);
    }
}
