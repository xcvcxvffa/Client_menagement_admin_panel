<?php

namespace App\Livewire\FileManagement;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Folder;
use App\Models\Document;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FileManager extends Component
{
    use WithFileUploads;

    public $currentFolderId = null;
    public $uploadFiles = [];
    public $newFolderName = '';

    // Client & Project association (used for uploads and folder creation)
    public $clientId = null;
    public $projectId = null;

    // Rename
    public $renameName = '';
    public $renameId = null;
    public $renameType = ''; // 'folder' or 'document'

    // Move
    public $moveItemId = null;
    public $moveItemType = '';
    public $moveDestinationId = null; // null = root

    // Preview
    public $previewFileUrl = null;
    public $previewFileType = null;
    public $previewFileName = null;

    // Delete Confirmation
    public $deleteModalId = null;
    public $deleteModalType = null;
    public $deleteModalTitle = '';
    public $deleteModalDescription = '';

    // Bulk Delete
    public $bulkSelectedItems = [];
    public $bulkDeleteModalTitle = '';
    public $bulkDeleteModalDescription = '';

    // State
    public $viewMode = 'list';
    public $search = '';
    public $sortBy = 'name_asc';
    public $activeTab = 'all'; // all, recent, starred, shared, trash, google-drive, dropbox, settings
    public $filterType = null; // null, 'document', 'image', 'video', 'audio'

    // Sharing
    public $shareItemId = null;
    public $shareItemType = null;

    protected $rules = [
        'newFolderName'  => 'required|string|max:255',
        'renameName'     => 'required|string|max:255',
        'uploadFiles.*'  => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,webp,gif,svg,mp4,webm,mov,mp3,wav,ogg,m4a|max:102400',
    ];

    // Livewire lifecycle — debounce search
    protected $queryString = ['search', 'activeTab', 'sortBy', 'filterType', 'viewMode'];

    public function mount()
    {
        if (!Auth::check() || !Auth::user()->current_business_id) {
            abort(403, 'Unauthorized. Please set up your business first.');
        }
    }

    // ---------------------------------------------------------------
    // FOLDER NAVIGATION
    // ---------------------------------------------------------------

    public function enterFolder(?int $folderId)
    {
        if (is_null($folderId)) {
            $this->goToRoot();
            return;
        }

        $businessId = Auth::user()->current_business_id;
        // Security: verify folder belongs to this business
        $folder = Folder::where('business_id', $businessId)->find($folderId);
        if (!$folder) {
            $this->dispatch('notify', message: 'Folder not found.', type: 'error');
            return;
        }
        $this->currentFolderId = $folderId;
        $this->search = '';
        $this->filterType = null;
    }

    public function goToRoot()
    {
        $this->currentFolderId = null;
        $this->search = '';
        $this->filterType = null;
    }

    public function navigateUp()
    {
        if ($this->currentFolderId) {
            $current = Folder::find($this->currentFolderId);
            $this->currentFolderId = $current ? $current->parent_id : null;
        }
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->currentFolderId = null;
        $this->search = '';
        $this->filterType = null;
    }

    public function setFilterType(?string $type)
    {
        $this->filterType = $type;
        $this->activeTab = 'all';
        $this->search = '';
        $this->currentFolderId = null;
    }

    public function goToBreadcrumb($folderId)
    {
        $this->currentFolderId = $folderId;
        $this->search = '';
        $this->filterType = null;
    }

    // ---------------------------------------------------------------
    // FOLDER CREATE
    // ---------------------------------------------------------------

    public function createFolder()
    {
        $this->validateOnly('newFolderName');

        $businessId = Auth::user()->current_business_id;

        // Prevent duplicate folder names in same location
        $exists = Folder::where('business_id', $businessId)
            ->where('parent_id', $this->currentFolderId)
            ->where('name', $this->newFolderName)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            $this->addError('newFolderName', 'A folder with this name already exists here.');
            return;
        }

        Folder::create([
            'business_id' => $businessId,
            'user_id'     => Auth::id(),
            'client_id'   => $this->clientId ?: null,
            'project_id'  => $this->projectId ?: null,
            'parent_id'   => $this->currentFolderId,
            'name'        => $this->newFolderName,
        ]);

        $this->newFolderName = '';
        $this->clientId = null;
        $this->projectId = null;
        $this->dispatch('close-modal', 'new-folder-modal');
        $this->dispatch('notify', message: 'Folder created successfully.', type: 'success');
    }

    // ---------------------------------------------------------------
    // FILE UPLOAD
    // ---------------------------------------------------------------

    public function updatedUploadFiles()
    {
        $this->saveUploads();
    }

    public function saveUploads()
    {
        if (empty($this->uploadFiles)) return;

        try {
            $this->validateOnly('uploadFiles.*');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->uploadFiles = [];
            $this->dispatch('files-uploaded');
            throw $e;
        }

        $businessId = Auth::user()->current_business_id;
        $maxStorage = config('app.storage_limit_bytes', 15 * 1024 * 1024 * 1024); // 15 GB default

        $currentSize = Document::where('business_id', $businessId)->sum('file_size');

        $uploadedCount = 0;

        foreach ($this->uploadFiles as $file) {
            $size = $file->getSize();

            if (($currentSize + $size) > $maxStorage) {
                $this->addError('uploadFiles', 'Storage limit exceeded. Cannot upload "' . $file->getClientOriginalName() . '".');
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $extension    = strtolower($file->getClientOriginalExtension());
            $mimeType     = $file->getMimeType();
            $fileType     = $this->determineFileType($mimeType, $extension);

            // Secure unique stored filename
            $storedName = Str::uuid() . '.' . $extension;
            $storagePath = 'documents/' . $businessId;
            $path = $file->storeAs($storagePath, $storedName, 'public');

            Document::create([
                'business_id'  => $businessId,
                'user_id'      => Auth::id(),
                'client_id'    => $this->clientId ?: null,
                'project_id'   => $this->projectId ?: null,
                'folder_id'    => $this->currentFolderId,
                'original_name' => $originalName,
                'stored_name'  => $storedName,
                'file_path'    => $path,
                'disk'         => 'public',
                'extension'    => $extension,
                'mime_type'    => $mimeType,
                'file_size'    => $size,
                'file_type'    => $fileType,
                'is_starred'   => false,
                'is_shared'    => false,
            ]);

            $currentSize += $size;
            $uploadedCount++;
        }

        $this->uploadFiles = [];
        // Reset file input in browser via JS event
        $this->dispatch('files-uploaded');

        if ($uploadedCount > 0) {
            $this->dispatch('notify', message: $uploadedCount . ' file(s) uploaded successfully.', type: 'success');
        }
    }

    private function determineFileType(string $mime, string $ext): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';

        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        if (in_array($ext, $docExts)) return 'document';

        return 'other';
    }

    // ---------------------------------------------------------------
    // FILE ACTIONS
    // ---------------------------------------------------------------

    public function previewItem(int $id)
    {
        $businessId = Auth::user()->current_business_id;
        $document = Document::where('business_id', $businessId)->find($id);

        if (!$document) {
            $this->dispatch('notify', message: 'File not found.', type: 'error');
            return;
        }

        $disk = $document->disk ?? 'public';

        if (!Storage::disk($disk)->exists($document->file_path)) {
            $this->dispatch('notify', message: 'Physical file is missing from storage.', type: 'error');
            return;
        }

        $this->previewFileUrl  = Storage::disk($disk)->url($document->file_path);
        $this->previewFileType = $document->file_type ?? 'other';
        $this->previewFileName = $document->original_name;
        $this->dispatch('open-modal', 'preview-modal');
    }

    /**
     * Download: Livewire cannot return binary responses.
     * We redirect to a dedicated download route.
     */
    public function downloadFile(int $id)
    {
        $businessId = Auth::user()->current_business_id;
        $document = Document::where('business_id', $businessId)->find($id);

        if (!$document) {
            $this->dispatch('notify', message: 'File not found.', type: 'error');
            return;
        }

        // Redirect to the dedicated download controller route
        return $this->redirect(route('files.download', ['id' => $id]), navigate: false);
    }

    public function toggleStar(int $id)
    {
        $document = Document::where('business_id', Auth::user()->current_business_id)->find($id);
        if ($document) {
            $document->update(['is_starred' => !$document->is_starred]);
            $msg = $document->is_starred ? 'File starred.' : 'File unstarred.';
            $this->dispatch('notify', message: $msg, type: 'success');
        }
    }

    // ---------------------------------------------------------------
    // RENAME
    // ---------------------------------------------------------------

    public function startRename(int $id, string $type, string $currentName)
    {
        $this->renameId   = $id;
        $this->renameType = $type;
        $this->renameName = $currentName;
        $this->resetErrorBag('renameName');
        $this->dispatch('open-modal', 'rename-modal');
    }

    public function saveRename()
    {
        $this->validateOnly('renameName');
        $businessId = Auth::user()->current_business_id;

        if ($this->renameType === 'folder') {
            $folder = Folder::where('business_id', $businessId)->find($this->renameId);
            if (!$folder) {
                $this->dispatch('notify', message: 'Folder not found.', type: 'error');
                return;
            }
            // Check for duplicates in same location
            $exists = Folder::where('business_id', $businessId)
                ->where('parent_id', $folder->parent_id)
                ->where('name', $this->renameName)
                ->where('id', '!=', $folder->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $this->addError('renameName', 'A folder with this name already exists here.');
                return;
            }
            $folder->update(['name' => $this->renameName]);

        } elseif ($this->renameType === 'document') {
            $document = Document::where('business_id', $businessId)->find($this->renameId);
            if (!$document) {
                $this->dispatch('notify', message: 'File not found.', type: 'error');
                return;
            }
            // Preserve the original file extension
            $originalExt = $document->extension;
            $newName = $this->renameName;
            if ($originalExt && !str_ends_with(strtolower($newName), '.' . $originalExt)) {
                // Strip any extension the user may have typed, re-append the real one
                $nameWithoutExt = pathinfo($newName, PATHINFO_FILENAME);
                $newName = $nameWithoutExt . '.' . $originalExt;
            }
            $document->update(['original_name' => $newName]);
        }

        $this->dispatch('close-modal', 'rename-modal');
        $this->dispatch('notify', message: ucfirst($this->renameType) . ' renamed successfully.', type: 'success');
    }

    // ---------------------------------------------------------------
    // DELETE & TRASH
    // ---------------------------------------------------------------

    public function confirmDelete(int $id, string $type)
    {
        $this->deleteModalId = $id;
        $this->deleteModalType = $type;
        
        if ($this->activeTab === 'trash') {
            $this->deleteModalTitle = 'Permanently Delete';
            $this->deleteModalDescription = 'Are you sure you want to permanently delete this ' . $type . '? This action cannot be undone.';
        } else {
            $this->deleteModalTitle = 'Move to Trash';
            $this->deleteModalDescription = 'Are you sure you want to move this ' . $type . ' to the trash?';
        }
        
        $this->dispatch('open-modal', 'delete-modal');
    }

    public function confirmEmptyTrash()
    {
        $this->deleteModalType = 'empty_trash';
        $this->deleteModalTitle = 'Empty Trash';
        $this->deleteModalDescription = 'Are you sure you want to permanently empty the trash? This cannot be undone.';
        $this->dispatch('open-modal', 'delete-modal');
    }

    public function executeDelete()
    {
        if ($this->deleteModalType === 'empty_trash') {
            $this->emptyTrash();
        } elseif ($this->deleteModalId && $this->deleteModalType) {
            $this->deleteItem($this->deleteModalId, $this->deleteModalType);
        }
        $this->dispatch('close-modal', 'delete-modal');
    }

    public function confirmBulkDelete(array $items)
    {
        if (empty($items)) return;
        
        $this->bulkSelectedItems = $items;
        $count = count($items);
        
        if ($this->activeTab === 'trash') {
            $this->bulkDeleteModalTitle = 'Permanently Delete';
            $this->bulkDeleteModalDescription = "Are you sure you want to permanently delete these $count items? This action cannot be undone.";
        } else {
            $this->bulkDeleteModalTitle = 'Move to Trash';
            $this->bulkDeleteModalDescription = "Are you sure you want to move $count items to the trash?";
        }
        
        $this->dispatch('open-modal', 'bulk-delete-modal');
    }

    public function executeBulkDelete()
    {
        if (empty($this->bulkSelectedItems)) return;

        $count = 0;
        foreach ($this->bulkSelectedItems as $itemStr) {
            $parts = explode(':', $itemStr);
            if (count($parts) === 2) {
                $type = $parts[0];
                $id = (int)$parts[1];
                $this->deleteItem($id, $type, true);
                $count++;
            }
        }

        $this->bulkSelectedItems = [];
        $this->dispatch('close-modal', 'bulk-delete-modal');
        $this->dispatch('items-deleted'); // To trigger frontend reset
        
        $msg = $this->activeTab === 'trash' ? "$count items permanently deleted." : "$count items moved to trash.";
        $this->dispatch('notify', message: $msg, type: 'success');
    }

    public function deleteItem(int $id, string $type, bool $silent = false)
    {
        $businessId = Auth::user()->current_business_id;

        if ($this->activeTab === 'trash') {
            // Permanent delete
            if ($type === 'folder') {
                $folder = Folder::withTrashed()->where('business_id', $businessId)->find($id);
                if ($folder) {
                    $this->permanentlyDeleteFolder($folder);
                    if (!$silent) $this->dispatch('notify', message: 'Folder permanently deleted.', type: 'success');
                }
            } elseif ($type === 'document') {
                $document = Document::withTrashed()->where('business_id', $businessId)->find($id);
                if ($document) {
                    $this->deletePhysicalFile($document);
                    $document->forceDelete();
                    if (!$silent) $this->dispatch('notify', message: 'File permanently deleted.', type: 'success');
                }
            }
        } else {
            // Soft delete → Trash
            if ($type === 'folder') {
                $folder = Folder::where('business_id', $businessId)->find($id);
                if ($folder) {
                    $folder->delete();
                    if (!$silent) $this->dispatch('notify', message: 'Folder moved to trash.', type: 'success');
                }
            } elseif ($type === 'document') {
                $document = Document::where('business_id', $businessId)->find($id);
                if ($document) {
                    $document->delete();
                    if (!$silent) $this->dispatch('notify', message: 'File moved to trash.', type: 'success');
                }
            }
        }
    }

    private function deletePhysicalFile(Document $document): void
    {
        $disk = $document->disk ?? 'public';
        if (Storage::disk($disk)->exists($document->file_path)) {
            Storage::disk($disk)->delete($document->file_path);
        }
    }

    private function permanentlyDeleteFolder(Folder $folder): void
    {
        // Recursively delete children
        foreach ($folder->children()->withTrashed()->get() as $child) {
            $this->permanentlyDeleteFolder($child);
        }
        // Delete all documents in this folder
        foreach ($folder->documents()->withTrashed()->get() as $doc) {
            $this->deletePhysicalFile($doc);
            $doc->forceDelete();
        }
        $folder->forceDelete();
    }

    public function emptyTrash()
    {
        $businessId = Auth::user()->current_business_id;

        // Permanently delete all trashed folders (root level only - recursion handles children)
        $trashedFolders = Folder::onlyTrashed()
            ->where('business_id', $businessId)
            ->get();
        foreach ($trashedFolders as $folder) {
            $this->permanentlyDeleteFolder($folder);
        }

        // Permanently delete remaining trashed documents (not inside deleted folders)
        $trashedDocs = Document::onlyTrashed()
            ->where('business_id', $businessId)
            ->get();
        foreach ($trashedDocs as $doc) {
            $this->deletePhysicalFile($doc);
            $doc->forceDelete();
        }

        $this->dispatch('notify', message: 'Trash emptied successfully.', type: 'success');
    }

    public function restoreItem(int $id, string $type)
    {
        $businessId = Auth::user()->current_business_id;

        if ($type === 'folder') {
            $folder = Folder::withTrashed()->where('business_id', $businessId)->find($id);
            if ($folder) {
                $folder->restore();
                $this->dispatch('notify', message: 'Folder restored.', type: 'success');
            }
        } elseif ($type === 'document') {
            $document = Document::withTrashed()->where('business_id', $businessId)->find($id);
            if ($document) {
                $document->restore();
                $this->dispatch('notify', message: 'File restored.', type: 'success');
            }
        }
    }

    // ---------------------------------------------------------------
    // MOVE
    // ---------------------------------------------------------------

    public function startMove(int $id, string $type)
    {
        $this->moveItemId       = $id;
        $this->moveItemType     = $type;
        $this->moveDestinationId = $this->currentFolderId;
        $this->resetErrorBag('moveDestinationId');
        $this->dispatch('open-modal', 'move-modal');
    }

    public function executeMove()
    {
        $businessId = Auth::user()->current_business_id;

        if ($this->moveItemType === 'folder') {
            // Cannot move folder into itself
            if ((string) $this->moveItemId === (string) $this->moveDestinationId) {
                $this->addError('moveDestinationId', 'Cannot move a folder into itself.');
                return;
            }

            // Cannot move folder into its own descendant
            if ($this->moveDestinationId) {
                $dest = Folder::find($this->moveDestinationId);
                while ($dest) {
                    if ($dest->id == $this->moveItemId) {
                        $this->addError('moveDestinationId', 'Cannot move a folder into its own subfolder.');
                        return;
                    }
                    $dest = $dest->parent;
                }
            }

            $folder = Folder::where('business_id', $businessId)->find($this->moveItemId);
            if ($folder) {
                $folder->update(['parent_id' => $this->moveDestinationId ?: null]);
            }
        } elseif ($this->moveItemType === 'document') {
            $document = Document::where('business_id', $businessId)->find($this->moveItemId);
            if ($document) {
                $document->update(['folder_id' => $this->moveDestinationId ?: null]);
            }
        }

        $this->dispatch('close-modal', 'move-modal');
        $this->dispatch('notify', message: ucfirst($this->moveItemType) . ' moved successfully.', type: 'success');
    }

    // ---------------------------------------------------------------
    // SHARE
    // ---------------------------------------------------------------

    public function startShare(int $id, string $type)
    {
        $this->shareItemId   = $id;
        $this->shareItemType = $type;
        $this->dispatch('open-modal', 'share-modal');
    }

    public function executeShare()
    {
        $businessId = Auth::user()->current_business_id;

        if ($this->shareItemType === 'document') {
            $document = Document::where('business_id', $businessId)->find($this->shareItemId);
            if ($document) {
                $document->update(['is_shared' => true]);
            }
        }

        $this->dispatch('close-modal', 'share-modal');
        $this->dispatch('notify', message: 'File shared successfully.', type: 'success');
    }

    // ---------------------------------------------------------------
    // BREADCRUMBS
    // ---------------------------------------------------------------

    public function getBreadcrumbsProperty(): array
    {
        $breadcrumbs = [];
        $current = $this->currentFolderId ? Folder::find($this->currentFolderId) : null;

        while ($current) {
            array_unshift($breadcrumbs, $current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    // ---------------------------------------------------------------
    // RENDER
    // ---------------------------------------------------------------

    public function render()
    {
        $businessId = Auth::user()->current_business_id;

        // Base queries
        $foldersQuery   = Folder::with(['client', 'project'])->withCount('documents')
            ->where('business_id', $businessId);
        $documentsQuery = Document::with(['user', 'client', 'project'])
            ->where('business_id', $businessId);

        // ---- TAB / FILTER LOGIC ----
        if ($this->activeTab === 'trash') {
            $foldersQuery->onlyTrashed();
            $documentsQuery->onlyTrashed();

        } elseif ($this->activeTab === 'starred') {
            $foldersQuery->whereRaw('1 = 0');
            $documentsQuery->where('is_starred', true);

        } elseif ($this->activeTab === 'shared') {
            $foldersQuery->whereRaw('1 = 0');
            $documentsQuery->where('is_shared', true);

        } elseif ($this->activeTab === 'recent') {
            $foldersQuery->whereRaw('1 = 0');
            $documentsQuery->where('created_at', '>=', now()->subDays(7));

        } else {
            // 'all' tab
            if (empty($this->search)) {
                if ($this->filterType) {
                    // File type filter (DOC/IMG/VID buttons)
                    $foldersQuery->whereRaw('1 = 0');
                    $documentsQuery->where('file_type', $this->filterType);
                } else {
                    // Normal folder browsing
                    $foldersQuery->where('parent_id', $this->currentFolderId);
                    $documentsQuery->where('folder_id', $this->currentFolderId);
                }
            }
        }

        // ---- GLOBAL SEARCH LOGIC ----
        if (!empty($this->search) && $this->activeTab !== 'trash') {
            $search = $this->search;

            // Extension filter: search starts with '.'
            if (str_starts_with($search, '.')) {
                $ext = ltrim($search, '.');
                $foldersQuery->whereRaw('1 = 0');
                $documentsQuery->where('extension', $ext);
            } else {
                $foldersQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', '%' . $search . '%')->orWhere('company_name', 'like', '%' . $search . '%'))
                      ->orWhereHas('project', fn ($pq) => $pq->where('name', 'like', '%' . $search . '%'));
                });
                $documentsQuery->where(function ($q) use ($search) {
                    $q->where('original_name', 'like', '%' . $search . '%')
                      ->orWhere('extension', 'like', '%' . $search . '%')
                      ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', '%' . $search . '%')->orWhere('company_name', 'like', '%' . $search . '%'))
                      ->orWhereHas('project', fn ($pq) => $pq->where('name', 'like', '%' . $search . '%'));
                });
            }
        }

        // ---- SORTING ----
        switch ($this->sortBy) {
            case 'name_desc':
                $foldersQuery->orderBy('name', 'desc');
                $documentsQuery->orderBy('original_name', 'desc');
                break;
            case 'date_asc':
                $foldersQuery->orderBy('created_at', 'asc');
                $documentsQuery->orderBy('created_at', 'asc');
                break;
            case 'date_desc':
                $foldersQuery->orderBy('created_at', 'desc');
                $documentsQuery->orderBy('created_at', 'desc');
                break;
            case 'size_asc':
                $foldersQuery->orderBy('name', 'asc');
                $documentsQuery->orderBy('file_size', 'asc');
                break;
            case 'size_desc':
                $foldersQuery->orderBy('name', 'asc');
                $documentsQuery->orderBy('file_size', 'desc');
                break;
            default: // name_asc
                $foldersQuery->orderBy('name', 'asc');
                $documentsQuery->orderBy('original_name', 'asc');
                break;
        }

        $folders   = $foldersQuery->get();
        $documents = $documentsQuery->get();

        // ---- STORAGE STATISTICS (exclude trashed) ----
        $statsRaw = Document::where('business_id', $businessId)
            ->selectRaw("
                COALESCE(SUM(file_size), 0) as total_size,
                COALESCE(SUM(CASE WHEN file_type = 'image'    THEN file_size ELSE 0 END), 0) as image_size,
                COALESCE(SUM(CASE WHEN file_type = 'video'    THEN file_size ELSE 0 END), 0) as video_size,
                COALESCE(SUM(CASE WHEN file_type = 'audio'    THEN file_size ELSE 0 END), 0) as audio_size,
                COALESCE(SUM(CASE WHEN file_type = 'document' THEN file_size ELSE 0 END), 0) as doc_size,
                COALESCE(SUM(CASE WHEN file_type NOT IN ('image','video','audio','document') OR file_type IS NULL THEN file_size ELSE 0 END), 0) as other_size,
                COUNT(*) as total_count,
                COALESCE(SUM(CASE WHEN file_type = 'image'    THEN 1 ELSE 0 END), 0) as image_count,
                COALESCE(SUM(CASE WHEN file_type = 'video'    THEN 1 ELSE 0 END), 0) as video_count,
                COALESCE(SUM(CASE WHEN file_type = 'audio'    THEN 1 ELSE 0 END), 0) as audio_count,
                COALESCE(SUM(CASE WHEN file_type = 'document' THEN 1 ELSE 0 END), 0) as doc_count,
                COALESCE(SUM(CASE WHEN file_type NOT IN ('image','video','audio','document') OR file_type IS NULL THEN 1 ELSE 0 END), 0) as other_count
            ")
            ->first();

        $maxStorage = config('app.storage_limit_bytes', 15 * 1024 * 1024 * 1024);
        $totalSize  = (int) $statsRaw->total_size;
        $usedPct    = $maxStorage > 0 ? min(100, round(($totalSize / $maxStorage) * 100, 1)) : 0;

        $stats = [
            'total_size'     => $totalSize,
            'max_storage'    => $maxStorage,
            'used_percentage' => $usedPct,
            'images'  => ['count' => (int) $statsRaw->image_count, 'size' => (int) $statsRaw->image_size],
            'videos'  => ['count' => (int) $statsRaw->video_count, 'size' => (int) $statsRaw->video_size],
            'audio'   => ['count' => (int) $statsRaw->audio_count, 'size' => (int) $statsRaw->audio_size],
            'docs'    => ['count' => (int) $statsRaw->doc_count,   'size' => (int) $statsRaw->doc_size],
            'others'  => ['count' => (int) $statsRaw->other_count, 'size' => (int) $statsRaw->other_size],
        ];

        // ---- SIDE DATA ----
        $clients    = Client::where('business_id', $businessId)->orderBy('name')->get();
        $projects   = Project::where('business_id', $businessId)->orderBy('name')->get();

        // All folders for Move modal — excluding the item being moved and its descendants
        $allFolders = Folder::where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        if ($this->moveItemType === 'folder' && $this->moveItemId) {
            $invalidIds = [$this->moveItemId];
            $getDescendants = function($parentId) use (&$getDescendants, $allFolders, &$invalidIds) {
                foreach ($allFolders as $f) {
                    if ($f->parent_id == $parentId) {
                        $invalidIds[] = $f->id;
                        $getDescendants($f->id);
                    }
                }
            };
            $getDescendants($this->moveItemId);
            $allFolders = $allFolders->reject(fn($f) => in_array($f->id, $invalidIds));
        }

        return view('livewire.file-management.file-manager', [
            'folders'    => $folders,
            'documents'  => $documents,
            'breadcrumbs' => $this->breadcrumbs,
            'stats'      => $stats,
            'clients'    => $clients,
            'projects'   => $projects,
            'allFolders' => $allFolders,
        ])->layout('layouts.app');
    }
}

