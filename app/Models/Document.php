<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'user_id',
        'client_id',
        'project_id',
        'folder_id',
        'original_name',
        'stored_name',
        'file_path',
        'disk',
        'extension',
        'mime_type',
        'file_size',
        'file_type',
        'is_starred',
        'is_shared',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
