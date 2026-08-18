<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\Folder;
use Illuminate\Support\Facades\Storage;

class AuditFileManagement extends Command
{
    protected $signature = 'files:audit';
    protected $description = 'Audit File Management module';

    public function handle()
    {
        $this->info('=== FILE MANAGEMENT AUDIT ===');
        $docs = Document::withTrashed()->get();
        $this->info('Total documents: ' . $docs->count());
        foreach ($docs as $doc) {
            $physicalExists = Storage::disk($doc->disk ?? 'public')->exists($doc->file_path);
            $this->line("Doc #" . $doc->id . " | path:" . $doc->file_path . " | stored:" . ($doc->stored_name ?? 'NULL') . " | ext:" . ($doc->extension ?? 'NULL') . " | type:" . ($doc->file_type ?? 'NULL') . " | physical:" . ($physicalExists ? 'YES' : 'NO'));
        }
        $folders = Folder::withTrashed()->get();
        $this->info('Total folders: ' . $folders->count());
        foreach ($folders as $f) {
            $this->line("Folder #" . $f->id . " | name:" . $f->name . " | parent:" . ($f->parent_id ?? 'NULL') . " | user:" . ($f->user_id ?? 'NULL'));
        }
    }
}
