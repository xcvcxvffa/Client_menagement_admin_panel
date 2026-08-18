<?php

use Livewire\Volt\Component;
use App\Models\Content;
use App\Models\ContentApproval;
use App\Models\ContentRevision;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $contentId;
    public $content;
    
    // Status Transitions
    public $statusColors = [
        'idea' => 'bg-gray-100 text-gray-800',
        'brief' => 'bg-purple-100 text-purple-800',
        'assigned' => 'bg-indigo-100 text-indigo-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'internal_review' => 'bg-yellow-100 text-yellow-800',
        'changes_requested' => 'bg-red-100 text-red-800',
        'client_approval' => 'bg-orange-100 text-orange-800',
        'approved' => 'bg-green-100 text-green-800',
        'scheduled' => 'bg-teal-100 text-teal-800',
        'published' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ];

    public $showApprovalModal = false;
    public $approvalType = 'internal'; // 'internal' or 'client'
    public $approvalStatus = 'approved';
    public $approvalComment = '';
    
    public $newTaskTitle = '';
    public $newTaskAssignee = '';

    public function mount($contentId)
    {
        $this->contentId = $contentId;
        $this->loadContent();
    }
    
    public function loadContent()
    {
        $businessId = Auth::user()->current_business_id;
        $this->content = Content::with([
            'client', 'project', 'retainer', 
            'contentType', 'platform', 'assignee', 'creator', 
            'tasks.assignee', 'approvals.creator', 'revisions.creator'
        ])
        ->where('business_id', $businessId)
        ->findOrFail($this->contentId);
    }
    
    public function updateStatus($newStatus)
    {
        // Simple status update without approval record
        $this->content->update(['status' => $newStatus]);
        
        \App\Models\ActivityLog::create([
            'business_id' => $this->content->business_id,
            'user_id' => Auth::id(),
            'description' => 'Updated status to: ' . ucwords(str_replace('_', ' ', $newStatus)),
            'subject_type' => Content::class,
            'subject_id' => $this->content->id,
        ]);
        
        $this->loadContent();
        session()->flash('message', 'Status updated successfully.');
    }
    
    public function openApprovalModal($type)
    {
        $this->approvalType = $type;
        $this->approvalStatus = 'approved';
        $this->approvalComment = '';
        $this->showApprovalModal = true;
    }
    
    public function submitApproval()
    {
        $this->validate([
            'approvalStatus' => 'required|in:approved,changes_requested',
            'approvalComment' => 'nullable|string',
        ]);
        
        // Create approval record
        ContentApproval::create([
            'business_id' => $this->content->business_id,
            'content_id' => $this->content->id,
            'type' => $this->approvalType,
            'status' => $this->approvalStatus,
            'comment' => $this->approvalComment,
            'created_by' => Auth::id(),
        ]);
        
        // Calculate new content status
        $newStatus = $this->content->status;
        
        if ($this->approvalStatus === 'changes_requested') {
            $newStatus = 'changes_requested';
        } else {
            // Approved
            if ($this->approvalType === 'internal') {
                $newStatus = 'client_approval';
            } elseif ($this->approvalType === 'client') {
                $newStatus = 'approved';
            }
        }
        
        $this->content->update(['status' => $newStatus]);
        
        \App\Models\ActivityLog::create([
            'business_id' => $this->content->business_id,
            'user_id' => Auth::id(),
            'description' => 'Submitted ' . $this->approvalType . ' approval: ' . $this->approvalStatus,
            'subject_type' => Content::class,
            'subject_id' => $this->content->id,
        ]);
        
        $this->showApprovalModal = false;
        $this->loadContent();
        session()->flash('message', 'Approval submitted successfully.');
    }
    
    public function createTask()
    {
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskAssignee' => 'nullable|exists:users,id',
        ]);
        
        Task::create([
            'title' => $this->newTaskTitle,
            'assigned_to' => $this->newTaskAssignee ?: null,
            'status' => 'to_do',
            'priority' => 'medium',
            'taskable_type' => Content::class,
            'taskable_id' => $this->content->id,
            // also set project_id if content belongs to project to keep legacy tasks working if needed, but not strictly required
            'project_id' => $this->content->project_id ?: null,
        ]);
        
        $this->newTaskTitle = '';
        $this->newTaskAssignee = '';
        $this->loadContent();
        session()->flash('task_message', 'Task created successfully.');
    }
    
    public function with()
    {
        return [
            'users' => User::whereHas('businesses', function($q) {
                $q->where('businesses.id', Auth::user()->current_business_id);
            })->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Messages -->
    @if (session()->has('message'))
        <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg shadow-sm" role="alert">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header Section -->
    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full {{ $statusColors[$content->status] ?? 'bg-gray-100 text-gray-800' }}">
                    {{ ucwords(str_replace('_', ' ', $content->status)) }}
                </span>
                <span class="text-[12px] text-gray-500 font-medium px-2 py-0.5 rounded-full {{ $content->priority === 'high' ? 'bg-red-50 text-red-600' : ($content->priority === 'medium' ? 'bg-yellow-50 text-yellow-600' : 'bg-gray-50 text-gray-600') }}">
                    {{ ucfirst($content->priority) }} Priority
                </span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $content->title }}</h1>
        </div>
        
        <!-- Workflow Actions -->
        <div class="flex flex-wrap gap-2">
            @if($content->status === 'idea')
                <button wire:click="updateStatus('brief')" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold rounded-lg shadow-sm transition">Move to Brief</button>
            @elseif($content->status === 'brief')
                <button wire:click="updateStatus('assigned')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-lg shadow-sm transition">Assign Work</button>
            @elseif($content->status === 'assigned' || $content->status === 'changes_requested')
                <button wire:click="updateStatus('in_progress')" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-lg shadow-sm transition">Start Progress</button>
            @elseif($content->status === 'in_progress')
                <button wire:click="updateStatus('internal_review')" class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold rounded-lg shadow-sm transition">Submit for Review</button>
            @elseif($content->status === 'internal_review')
                @can('review content')
                <button wire:click="openApprovalModal('internal')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">Internal Review</button>
                @endcan
            @elseif($content->status === 'client_approval')
                @can('approve content')
                <button wire:click="openApprovalModal('client')" class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">Client Approval</button>
                @endcan
            @elseif($content->status === 'approved')
                @can('schedule content')
                <button wire:click="updateStatus('scheduled')" class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-lg shadow-sm transition">Schedule</button>
                @endcan
            @elseif($content->status === 'scheduled')
                @can('publish content')
                <button wire:click="updateStatus('published')" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg shadow-sm transition">Mark Published</button>
                @endcan
            @endif
            
            <button wire:click="updateStatus('cancelled')" class="px-3 py-1.5 bg-gray-100 hover:bg-red-50 text-gray-500 hover:text-red-600 text-sm font-semibold rounded-lg shadow-sm transition">Cancel</button>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Details -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Brief & Caption -->
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Description</h3>
                    <p class="text-gray-700 text-sm whitespace-pre-wrap">{{ $content->description ?: 'No description provided.' }}</p>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Content Brief</h3>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap font-mono">{{ $content->brief ?: 'No brief provided.' }}</div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Caption / Copy</h3>
                    <div class="bg-blue-50/50 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap">{{ $content->caption ?: 'No caption provided.' }}</div>
                </div>
            </div>
            
            <!-- Tasks -->
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Content Tasks</h3>
                
                @if (session()->has('task_message'))
                    <div class="p-3 mb-4 text-sm text-green-700 bg-green-50 rounded-lg">{{ session('task_message') }}</div>
                @endif
                
                <div class="space-y-3 mb-4">
                    @forelse($content->tasks as $task)
                        <div class="flex items-center justify-between p-3 border border-gray-100 rounded-lg bg-gray-50 hover:bg-white transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full border-2 {{ $task->status === 'completed' ? 'bg-green-500 border-green-500' : 'border-gray-300' }}"></div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ $task->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $task->assignee ? $task->assignee->name : 'Unassigned' }} &bull; Status: {{ ucwords(str_replace('_', ' ', $task->status)) }}</p>
                                </div>
                            </div>
                            <a href="#" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900">View Task</a>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 text-center py-4">No tasks assigned to this content yet.</div>
                    @endforelse
                </div>
                
                @can('create tasks')
                <div class="flex gap-2 items-center">
                    <input wire:model="newTaskTitle" type="text" placeholder="Add a new task..." class="flex-1 text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-custom-select wire:model="newTaskAssignee" placeholder="Assignee" class="w-40 mt-0"
                        :options="collect($users)->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray()" />
                    <button wire:click="createTask" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg shadow-sm">Add</button>
                </div>
                @endcan
            </div>
            
        </div>
        
        <!-- Right Column: Sidebar -->
        <div class="space-y-6">
            
            <!-- Info Card -->
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Information</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Client</div>
                        <div class="text-sm font-semibold text-gray-900">{{ $content->client->name ?? 'N/A' }}</div>
                    </div>
                    

                    @if($content->project)
                    <div>
                        <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Project</div>
                        <div class="text-sm font-semibold text-gray-900">{{ $content->project->name }}</div>
                    </div>
                    @elseif($content->retainer)
                    <div>
                        <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Retainer</div>
                        <div class="text-sm font-semibold text-gray-900">{{ $content->retainer->name }}</div>
                    </div>
                    @endif
                    

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Platform</div>
                            <span class="inline-block px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded">{{ $content->platform->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Type</div>
                            <span class="inline-block px-2 py-0.5 bg-purple-50 text-purple-700 text-xs font-bold rounded">{{ $content->contentType->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Assignee</div>
                        <div class="flex items-center gap-2 mt-1">
                            @if($content->assignee)
                                <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold">{{ substr($content->assignee->name, 0, 1) }}</div>
                                <span class="text-sm text-gray-700">{{ $content->assignee->name }}</span>
                            @else
                                <span class="text-sm text-gray-400">Unassigned</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-100">
                        <div>
                            <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Due Date</div>
                            <div class="text-sm text-gray-900 font-semibold">{{ $content->due_date ? $content->due_date->format('d M y') : '--' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Publish Date</div>
                            <div class="text-sm text-indigo-600 font-semibold">{{ $content->publish_date ? $content->publish_date->format('d M y') : '--' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Approval History -->
            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Approval History</h3>
                
                <div class="space-y-4">
                    @forelse($content->approvals()->latest()->get() as $approval)
                        <div class="relative pl-4 border-l-2 {{ $approval->status === 'approved' ? 'border-green-400' : 'border-red-400' }}">
                            <div class="absolute -left-1.5 top-1.5 w-2.5 h-2.5 rounded-full {{ $approval->status === 'approved' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ ucfirst($approval->type) }} {{ $approval->status === 'approved' ? 'Approved' : 'Requested Changes' }}</p>
                                    <p class="text-xs text-gray-500">{{ $approval->creator->name }} &bull; {{ $approval->created_at->format('d M y g:i A') }}</p>
                                </div>
                            </div>
                            @if($approval->comment)
                                <div class="mt-2 text-sm text-gray-700 bg-gray-50 p-2 rounded-lg italic">"{{ $approval->comment }}"</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 text-center py-2">No approvals recorded yet.</div>
                    @endforelse
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Approval Modal -->
    @if($showApprovalModal)
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">{{ ucfirst($approvalType) }} Approval</h3>
                <button wire:click="$set('showApprovalModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form wire:submit="submitApproval" class="p-6 space-y-4">
                <div>
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Decision</label>
                    <x-custom-select wire:model="approvalStatus" placeholder="Decision"
                        :options="[
                            ['id' => 'approved', 'name' => 'Approve Content'],
                            ['id' => 'changes_requested', 'name' => 'Request Changes']
                        ]" />
                </div>
                
                <div>
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">Feedback / Notes</label>
                    <textarea wire:model="approvalComment" rows="3" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional notes..."></textarea>
                </div>
                
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showApprovalModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700">Submit Decision</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
