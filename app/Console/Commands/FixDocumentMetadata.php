<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;

class FixDocumentMetadata extends Command
{
    protected $signature = 'files:fix-metadata';
    protected $description = 'Backfill missing extension, file_type, and stored_name for existing documents';

    private function determineFileType(string $mime, string $ext): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        if (in_array($ext, $docExts)) return 'document';
        $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        if (in_array($ext, $imgExts)) return 'image';
        $vidExts = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
        if (in_array($ext, $vidExts)) return 'video';
        $audExts = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
        if (in_array($ext, $audExts)) return 'audio';
        return 'other';
    }

    public function handle()
    {
        $docs = Document::withTrashed()
            ->where(function ($q) {
                $q->whereNull('extension')
                  ->orWhereNull('file_type')
                  ->orWhereNull('stored_name');
            })
            ->get();

        $this->info("Found {$docs->count()} documents needing metadata fix.");

        foreach ($docs as $doc) {
            $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
            $mime = $doc->mime_type ?? 'application/octet-stream';
            $type = $this->determineFileType($mime, $ext);
            $storedName = $doc->stored_name ?? basename($doc->file_path);

            Document::withTrashed()->where('id', $doc->id)->update([
                'extension'   => $ext ?: null,
                'file_type'   => $type,
                'stored_name' => $storedName,
            ]);

            $this->line("  Fixed Doc #{$doc->id}: ext={$ext}, type={$type}, stored={$storedName}");
        }

        $this->info('Done!');
    }
}
