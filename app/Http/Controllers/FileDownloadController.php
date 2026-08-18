<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileDownloadController extends Controller
{
    public function download(Request $request, int $id)
    {
        $user = Auth::user();

        if (!$user || !$user->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $document = Document::where('business_id', $user->current_business_id)
            ->find($id);

        if (!$document) {
            abort(404, 'File not found');
        }

        $disk = $document->disk ?? 'public';

        if (!Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'Physical file not found on storage');
        }

        $filename = $document->original_name ?? basename($document->file_path);

        return Storage::disk($disk)->download($document->file_path, $filename);
    }
}
