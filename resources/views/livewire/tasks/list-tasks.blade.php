<?php

use App\Models\Task;
use App\Models\Project;
use App\Models\Retainer;
use App\Models\User;
use App\Models\TeamMember;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with, mount};

state([
    'search' => '',
    'parentFilter' => 'all',
    'statusFilter' => 'all',
    'assigneeFilter' => 'all',
    'viewMode' => 'kanban', // kanban | list
    
    // Form fields for create/edit
    'taskParentId' => '',
    'taskTitle' => '',
    'taskDescription' => '',
    'taskPriority' => 'medium',
    'taskDueDate' => '',
    'taskDueTime' => '',
    'taskAssignedTo' => '',
    
    'editingTaskId' => null,
    'viewingTaskId' => null,
    'newComment' => '',
]);

$resetTaskForm = function () {
    $this->editingTaskId = null;
    $this->taskParentId = '';
    $this->taskTitle = '';
    $this->taskDescription = '';
    $this->taskPriority = 'medium';
    $this->taskDueDate = '';
    $this->taskDueTime = '';
    $this->taskAssignedTo = '';
};

$saveTask = function () {
    if ($this->editingTaskId) {
        \Illuminate\Support\Facades\Gate::authorize('edit tasks');
    } else {
        \Illuminate\Support\Facades\Gate::authorize('create tasks');
    }

    $this->validate([
        'taskParentId' => 'required|string',
        'taskTitle' => 'required|string|max:255',
        'taskDescription' => 'nullable|string',
        'taskPriority' => 'required|in:low,medium,high',
        'taskDueDate' => 'nullable|date',
        'taskAssignedTo' => 'nullable|exists:users,id',
    ]);

    $parts = explode(':', $this->taskParentId);
    $taskType = $parts[0] === 'Project' ? Project::class : Retainer::class;
    $taskId = $parts[1];

    if ($this->editingTaskId) {
        $task = Task::find($this->editingTaskId);
        if ($task) {
            $task->update([
                'project_id' => $taskType === Project::class ? $taskId : null,
                'taskable_type' => $taskType,
                'taskable_id' => $taskId,
                'title' => $this->taskTitle,
                'description' => $this->taskDescription ?: null,
                'priority' => $this->taskPriority,
                'due_date' => $this->taskDueDate ? ($this->taskDueDate . ($this->taskDueTime ? ' ' . $this->taskDueTime . ':00' : ' 00:00:00')) : null,
                'assigned_to' => $this->taskAssignedTo ?: null,
            ]);
            ActivityLog::create([
                'description' => "Updated task '{$task->title}'",
                'subject_id' => $task->taskable_id,
                'subject_type' => $task->taskable_type,
            ]);
            $this->dispatch('notify', message: 'Task updated successfully.', type: 'success');
        }
    } else {
        $maxOrder = Task::where('taskable_type', $taskType)->where('taskable_id', $taskId)->max('sort_order') ?? 0;
        $task = Task::create([
            'project_id' => $taskType === Project::class ? $taskId : null,
            'taskable_type' => $taskType,
            'taskable_id' => $taskId,
            'title' => $this->taskTitle,
            'description' => $this->taskDescription ?: null,
            'priority' => $this->taskPriority,
            'due_date' => $this->taskDueDate ? ($this->taskDueDate . ($this->taskDueTime ? ' ' . $this->taskDueTime . ':00' : ' 00:00:00')) : null,
            'assigned_to' => $this->taskAssignedTo ?: null,
            'sort_order' => $maxOrder + 1,
            'status' => 'todo',
        ]);
        ActivityLog::create([
            'description' => "Created task '{$task->title}'",
            'subject_id' => $task->taskable_id,
            'subject_type' => $task->taskable_type,
        ]);
        $this->dispatch('notify', message: 'Task created successfully.', type: 'success');
    }

    $this->resetTaskForm();
    $this->dispatch('close-modal');
};

$editTask = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('edit tasks');
    $task = Task::find($id);
    if ($task) {
        $this->editingTaskId = $task->id;
        $type = class_basename($task->taskable_type);
        $this->taskParentId = $type . ':' . $task->taskable_id;
        $this->taskTitle = $task->title;
        $this->taskDescription = $task->description;
        $this->taskPriority = $task->priority;
        $this->taskDueDate = $task->due_date?->format('Y-m-d');
        $this->taskDueTime = $task->due_date?->format('H:i') === '00:00' ? '' : $task->due_date?->format('H:i');
        $this->taskAssignedTo = $task->assigned_to ?? '';
        $this->dispatch('open-modal-edit');
    }
};

$deleteTask = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('delete tasks');
    $task = Task::find($id);
    if ($task) {
        $title = $task->title;
        $projectId = $task->project_id;
        $task->delete();
        ActivityLog::create([
            'description' => "Deleted task '{$title}'",
            'subject_id' => $task->taskable_id,
            'subject_type' => $task->taskable_type,
        ]);
        $this->dispatch('notify', message: 'Task deleted successfully.', type: 'success');
    }
};

$updateTaskStatus = function ($taskId, $newStatus) {
    \Illuminate\Support\Facades\Gate::authorize('status tasks');
    $task = Task::find($taskId);
    if ($task && in_array($newStatus, ['todo', 'in_progress', 'completed', 'on_hold', 'cancelled'])) {
        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'completed' ? now() : null,
        ]);
        ActivityLog::create([
            'description' => "Moved task '{$task->title}' to " . str_replace('_', ' ', $newStatus),
            'subject_id' => $task->project_id,
            'subject_type' => $task->taskable_type,
        ]);
    }
};

$viewTask = function ($id) {
    $this->viewingTaskId = $id;
    $this->dispatch('open-modal-view');
};

$addComment = function () {
    $this->validate([
        'newComment' => 'required|string',
    ]);

    if ($this->viewingTaskId) {
        \App\Models\TaskComment::create([
            'task_id' => $this->viewingTaskId,
            'user_id' => Auth::id(),
            'comment' => $this->newComment,
        ]);
        
        $task = Task::find($this->viewingTaskId);
        ActivityLog::create([
            'description' => "Commented on task '{$task->title}'",
            'subject_id' => $task->project_id,
            'subject_type' => $task->taskable_type,
        ]);

        $this->newComment = '';
        $this->dispatch('notify', message: 'Comment added.', type: 'success');
    }
};

with(function () {
    $businessId = Auth::user()->current_business_id;
    $query = Task::with(['taskable', 'assignee'])
        ->whereHasMorph('taskable', [Project::class, Retainer::class], function($q, $type) use ($businessId) {
            $q->where('business_id', $businessId);
        });

    if ($this->search) {
        $query->where(function($q) {
            $q->where('title', 'like', "%{$this->search}%")
              ->orWhereHasMorph('taskable', [Project::class, Retainer::class], fn($t) => $t->where('name', 'like', "%{$this->search}%"));
        });
    }

    if ($this->parentFilter !== 'all') {
        $parts = explode(':', $this->parentFilter);
        $type = $parts[0] === 'Project' ? Project::class : Retainer::class;
        $query->where('taskable_type', $type)->where('taskable_id', $parts[1]);
    }

    if ($this->statusFilter !== 'all') {
        $query->where('status', $this->statusFilter);
    }
    
    if ($this->assigneeFilter !== 'all') {
        if ($this->assigneeFilter === 'unassigned') {
            $query->whereNull('assigned_to');
        } else {
            $query->where('assigned_to', $this->assigneeFilter);
        }
    }

    $allTasks = $query->get();
    $groupedTasks = $allTasks->groupBy('status');

    return [
        'projects' => Project::where('status', '!=', 'migrated')->orderBy('name')->get(['id', 'name']),
        'retainers' => Retainer::orderBy('name')->get(['id', 'name']),
        'teamMembers' => TeamMember::with('user')->where('business_id', $businessId)->get()->map(fn($tm) => $tm->user),
        'tasks' => $allTasks,
        'groupedTasks' => $groupedTasks,
        'viewingTask' => $this->viewingTaskId ? Task::with(['taskable', 'assignee', 'comments.user'])->find($this->viewingTaskId) : null,
        'kanbanColumns' => [
            'todo' => [
                'label' => 'To Do',
                'icon' => '<svg class="w-4 h-4 text-gray-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
                'tasks' => $groupedTasks->get('todo', []),
                'empty_text' => 'No tasks to do'
            ],
            'in_progress' => [
                'label' => 'In Progress',
                'icon' => '<svg class="w-4 h-4 text-orange-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'tasks' => $groupedTasks->get('in_progress', []),
                'empty_text' => 'No tasks in progress'
            ],
            'completed' => [
                'label' => 'Completed',
                'icon' => '<svg class="w-4 h-4 text-emerald-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'tasks' => $groupedTasks->get('completed', []),
                'empty_text' => 'No completed tasks'
            ],
            'on_hold' => [
                'label' => 'On Hold',
                'icon' => '<svg class="w-4 h-4 text-rose-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'tasks' => $groupedTasks->get('on_hold', []),
                'empty_text' => 'No paused tasks'
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'icon' => '<svg class="w-4 h-4 text-gray-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>',
                'tasks' => $groupedTasks->get('cancelled', []),
                'empty_text' => 'No cancelled tasks'
            ],
        ],
    ];
});
?>

<div class="h-full flex flex-col min-h-0" x-data="{ 
        openModal: false, 
        isEdit: false,
        confirmDeleteModal: false,
        taskToDelete: null,
        draggedTaskId: null,
        dragStart(e, taskId) {
            this.draggedTaskId = taskId;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', taskId);
        },
        dragEnter(e) {
            e.preventDefault();
        },
        dragOver(e) {
            e.preventDefault();
        },
        drop(e, status) {
            e.preventDefault();
            if (this.draggedTaskId) {
                @this.call('updateTaskStatus', this.draggedTaskId, status);
            }
            this.draggedTaskId = null;
        }
     }"
     @open-modal-edit.window="openModal = true; isEdit = true"
     @close-modal.window="openModal = false; isEdit = false">
    @assets
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @endassets

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Task Management</h2>
            <p class="text-[14px] text-gray-500 dark:text-gray-400 mt-1">Create and assign tasks across your projects</p>
        </div>
        @can('create tasks')
        <button @click="openModal = true; isEdit = false; $wire.resetTaskForm()"
                class="inline-flex items-center justify-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-semibold rounded-lg shadow-sm transition-colors flex-shrink-0">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Task
        </button>
        @endcan
    </div>

    <!-- Filters Row -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <!-- Status Filter -->
        <x-custom-select wire:model.live="statusFilter" placeholder="All Statuses" class="w-full sm:w-44 mt-0 z-40"
            :options="[
                ['id' => 'all', 'name' => 'All Statuses'], 
                ['id' => 'todo', 'name' => 'To do'], 
                ['id' => 'in_progress', 'name' => 'In Progress'], 
                ['id' => 'completed', 'name' => 'Completed']
            ]" />

        <!-- Assignee Filter -->
        <x-custom-select wire:model.live="assigneeFilter" placeholder="All Team Members" class="w-full sm:w-48 mt-0 z-30"
            :options="collect([
                ['id' => 'all', 'name' => 'All Team Members'],
                ['id' => 'unassigned', 'name' => 'Unassigned']
            ])->concat($teamMembers->map(fn($m) => ['id' => (string)$m->id, 'name' => $m->name]))->toArray()" />

        <!-- Project Filter -->
        <x-custom-select wire:model.live="parentFilter" placeholder="All Projects" class="w-full sm:w-44 mt-0 z-20"
            :options="collect([
                ['id' => 'all', 'name' => 'All Projects']
            ])->concat($projects->map(fn($p) => ['id' => 'Project:'.$p->id, 'name' => 'Project - '.$p->name]))
              ->concat($retainers->map(fn($r) => ['id' => 'Retainer:'.$r->id, 'name' => 'Retainer - '.$r->name]))->toArray()" />
    </div>

    <!-- Flash Message -->
    @if(session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg class="w-5 h-5 mr-2.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- ====================== KANBAN BOARD VIEW ====================== -->
    <div class="relative flex-grow min-h-0">
        <div wire:loading.delay.long>
            <x-skeleton-loader type="tasks" />
        </div>
        <div wire:loading.remove.delay.long class="flex gap-6 overflow-x-auto pb-4 pt-2 flex-grow min-h-0">
        @foreach($kanbanColumns as $status => $column)
            <div class="flex-1 min-w-[320px] bg-white border border-gray-100 rounded-2xl flex flex-col max-h-[75vh]"
                 @dragover="dragOver"
                 @drop="drop($event, '{{ $status }}')">
                
                <!-- Column Header -->
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center">
                        @if(isset($column['icon']))
                            {!! $column['icon'] !!}
                        @endif
                        <h3 class="font-bold text-gray-800">{{ $column['label'] }} ({{ count($column['tasks']) }})</h3>
                    </div>
                </div>

                <!-- Column Body (Task List) -->
                <div class="p-4 overflow-y-auto flex-1 space-y-4 min-h-[150px]">
                    @foreach($column['tasks'] as $task)
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-150 border-l-4 border-l-[#ea580c] cursor-grab hover:shadow-md transition-shadow duration-200 group relative"
                             draggable="true"
                             @dragstart="dragStart($event, {{ $task->id }})">
                            
                            <!-- Task Title -->
                            <div class="flex items-start mb-2">
                                <svg class="w-4 h-4 text-[#ea580c] mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                <h4 class="font-bold text-[#ea580c] text-sm leading-snug cursor-pointer" wire:click="viewTask({{ $task->id }})">{{ $task->title }}</h4>
                            </div>

                            <!-- Meta -->
                            <div class="space-y-1.5 mb-4 pl-6">
                                <!-- Assignee -->
                                <div class="flex items-center text-xs text-gray-400">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    {{ $task->assignee->name ?? 'Unassigned' }}
                                </div>
                                <!-- Due Date -->
                                @if($task->due_date)
                                <div class="flex items-center text-xs text-gray-400">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    Due: {{ $task->due_date->format('d/m/Y') }}
                                </div>
                                @endif
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between pl-6 mt-1">
                                @if($task->status === 'todo')
                                    <button wire:click.stop="updateTaskStatus({{ $task->id }}, 'in_progress')" class="px-4 py-1 bg-[#ea580c] hover:bg-orange-700 text-white text-[10px] font-bold rounded-full transition-colors">Start</button>
                                @elseif($task->status === 'in_progress')
                                    <button wire:click.stop="updateTaskStatus({{ $task->id }}, 'completed')" class="px-4 py-1 bg-[#10b981] hover:bg-emerald-600 text-white text-[10px] font-bold rounded-full transition-colors">Complete</button>
                                @else
                                    <div></div>
                                @endif
                                
                                @can('delete tasks')
                                <button type="button" @click.stop="confirmDeleteModal = true; taskToDelete = {{ $task->id }}" class="text-gray-300 hover:text-gray-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </button>
                                @endcan
                            </div>
                            
                            <!-- Edit Button -->
                            <div class="absolute top-3 right-3 bg-white rounded transition-colors">
                                @can('edit tasks')
                                <button wire:click="editTask({{ $task->id }})" class="text-slate-300 hover:text-[#ea580c]">
                                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                    
                    @if(count($column['tasks']) === 0)
                        <div class="p-8 text-center text-slate-400 text-[14px] border border-dashed border-gray-200 dark:border-gray-800 rounded-xl mx-4 mb-4">
                            {{ $column['empty_text'] }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        </div>
    </div>

    <!-- ====================== CREATE / EDIT MODAL ====================== -->
    <div x-show="openModal"
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800"
             @click.away="openModal = false">

            <div class="flex items-center justify-between mb-5 border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-base font-bold text-gray-900 dark:text-white" x-text="isEdit ? 'Edit Task' : 'Create New Task'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="saveTask" class="space-y-4">
                <!-- Project -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Project *</label>
                    <x-custom-select wire:model="taskParentId" placeholder="— Select project —" class="w-full mt-0 z-40"
                        :options="collect([
                            ['id' => '', 'name' => '— Select project —']
                        ])->concat($projects->map(fn($p) => ['id' => 'Project:'.$p->id, 'name' => 'Project - '.$p->name]))
                          ->concat($retainers->map(fn($r) => ['id' => 'Retainer:'.$r->id, 'name' => 'Retainer - '.$r->name]))->toArray()" />
                    @error('taskParentId') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Title -->
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Task Title *</label>
                    <input type="text" wire:model="taskTitle" required placeholder="Enter task title"
                           class="w-full px-3.5 py-2.5 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white outline-none" />
                    @error('taskTitle') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Description</label>
                    <textarea wire:model="taskDescription" rows="3" placeholder="Task description and requirements..."
                              class="w-full px-3.5 py-2.5 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white outline-none"></textarea>
                </div>

                <!-- Priority & Assignee -->
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Priority *</label>
                        <x-custom-select wire:model="taskPriority" placeholder="Medium" class="w-full mt-0 z-30"
                            :options="[
                                ['id' => 'low', 'name' => 'Low'],
                                ['id' => 'medium', 'name' => 'Medium'],
                                ['id' => 'high', 'name' => 'High']
                            ]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Assign To *</label>
                        <x-custom-select wire:model="taskAssignedTo" placeholder="Select team member" class="w-full mt-0 z-30"
                            :options="collect([
                                ['id' => '', 'name' => 'Select team member']
                            ])->concat($teamMembers->map(fn($m) => ['id' => (string)$m->id, 'name' => $m->name]))->toArray()" />
                    </div>
                </div>

                <!-- Due Date & Time -->
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Due Date</label>
                        <x-date-picker wire:model="taskDueDate" placeholder="Date" class="w-full mt-0" />
                    </div>
                    
                    <!-- Custom Alpine Time Picker (Matches Image 2) -->
                    <div x-data="{
                        open: false,
                        hours: ['1','2','3','4','5','6','7','8','9','10','11','12'],
                        mins: ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'],
                        periods: ['AM', 'PM'],
                        
                        hour: '',
                        min: '',
                        period: 'AM',
                        
                        init() {
                            if ($wire.taskDueTime) {
                                let parts = $wire.taskDueTime.split(':');
                                let h = parseInt(parts[0]);
                                this.period = h >= 12 ? 'PM' : 'AM';
                                h = h % 12;
                                if (h === 0) h = 12;
                                this.hour = h.toString();
                                this.min = parts[1];
                            }
                            
                            $watch('hour', () => this.updateWire());
                            $watch('min', () => this.updateWire());
                            $watch('period', () => this.updateWire());
                        },
                        
                        updateWire() {
                            if (!this.hour || !this.min) return;
                            let h = parseInt(this.hour);
                            if (this.period === 'PM' && h !== 12) h += 12;
                            if (this.period === 'AM' && h === 12) h = 0;
                            let hStr = h.toString().padStart(2, '0');
                            $wire.set('taskDueTime', `${hStr}:${this.min}`);
                        }
                    }" class="relative w-full">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">&nbsp;</label>
                        <div class="relative" @click="open = !open" @click.away="open = false">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <input type="text" readonly :value="(hour && min) ? `${hour}:${min} ${period}` : ''" placeholder="Time" :class="open ? 'border-[#ea580c] ring-0' : 'border-gray-250 dark:border-gray-750'" class="w-full pl-9 pr-3 py-2.5 border bg-white dark:bg-gray-800 rounded-xl text-sm text-gray-900 dark:text-white outline-none cursor-pointer transition-colors" />
                        </div>

                        <!-- Dropdown panel -->
                        <div x-show="open" style="display: none;" class="absolute z-50 left-0 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] p-4 flex justify-between h-56">
                            
                            <!-- HR -->
                            <div class="flex flex-col items-center w-1/3">
                                <span class="text-[10px] font-bold text-gray-400 mb-2">HR</span>
                                <div class="overflow-y-auto w-full space-y-1 flex flex-col items-center scrollbar-hide pb-10">
                                    <template x-for="h in hours">
                                        <button type="button" @click.stop="hour = h" 
                                                :class="hour === h ? 'bg-[#ea580c] text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-gray-700'"
                                                class="w-10 py-1.5 rounded-lg text-sm text-center transition-colors" x-text="h"></button>
                                    </template>
                                </div>
                            </div>

                            <!-- MIN -->
                            <div class="flex flex-col items-center w-1/3 border-l border-r border-gray-100 dark:border-gray-700">
                                <span class="text-[10px] font-bold text-gray-400 mb-2">MIN</span>
                                <div class="overflow-y-auto w-full space-y-1 flex flex-col items-center scrollbar-hide pb-10">
                                    <template x-for="m in mins">
                                        <button type="button" @click.stop="min = m" 
                                                :class="min === m ? 'bg-[#ea580c] text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-gray-700'"
                                                class="w-10 py-1.5 rounded-lg text-sm text-center transition-colors" x-text="m"></button>
                                    </template>
                                </div>
                            </div>

                            <!-- AM/PM -->
                            <div class="flex flex-col items-center w-1/3 pt-6 space-y-2">
                                <template x-for="p in periods">
                                    <button type="button" @click.stop="period = p" 
                                             :class="period === p ? 'bg-[#ea580c] text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-gray-700'"
                                            class="w-10 py-1.5 rounded-lg text-sm text-center transition-colors" x-text="p"></button>
                                </template>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="pt-4 flex items-center justify-end space-x-3.5 border-t border-gray-100 dark:border-gray-800 mt-4">
                    <button type="button" @click="openModal = false"
                            class="px-4 py-2 border border-gray-250 dark:border-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl text-sm transition-colors duration-150">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white rounded-xl text-sm font-semibold transition-all duration-150 shadow-md">
                        Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-data="{ openViewModal: false }"
         @open-modal-view.window="openViewModal = true"
         x-show="openViewModal"
         class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
         style="display: none;">
         
         <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-2xl w-full flex flex-col max-h-[90vh] shadow-2xl border border-gray-100 dark:border-gray-800"
              @click.away="openViewModal = false; $wire.set('viewingTaskId', null)">
            
            @if($viewingTask)
            <!-- Header -->
            <div class="flex flex-shrink-0 items-center justify-between p-5 border-b border-gray-100 dark:border-gray-800">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $viewingTask->title }}</h3>
                    <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">{{ class_basename($viewingTask->taskable_type) }} — {{ $viewingTask->taskable->name ?? 'Unknown' }}</p>
                </div>
                <button @click="openViewModal = false; $wire.set('viewingTaskId', null)" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                
                <!-- Task Details -->
                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-150 dark:border-gray-800 flex flex-wrap gap-6 text-sm">
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ str_replace('_', ' ', ucfirst($viewingTask->status)) }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Priority</span>
                        <span class="font-bold {{ $viewingTask->priority === 'high' ? 'text-rose-500' : ($viewingTask->priority === 'medium' ? 'text-amber-500' : 'text-gray-500') }} uppercase">{{ $viewingTask->priority }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Due Date</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $viewingTask->due_date ? $viewingTask->due_date->format('M d, Y') : '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Assignee</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $viewingTask->assignee->name ?? 'Unassigned' }}</span>
                    </div>
                </div>

                @if($viewingTask->description)
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Description</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $viewingTask->description }}</p>
                    </div>
                @endif

                <!-- Comments Section -->
                <div class="border-t border-gray-150 dark:border-gray-800 pt-6">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Comments</h4>
                    
                    <div class="space-y-4 mb-6">
                        @forelse($viewingTask->comments as $comment)
                            <div class="flex space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-xs">
                                    {{ substr($comment->user->name ?? 'S', 0, 1) }}
                                </div>
                                <div class="flex-1 bg-gray-50 dark:bg-gray-800 p-3 rounded-xl rounded-tl-none">
                                    <div class="flex items-baseline justify-between mb-1">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $comment->user->name ?? 'System' }}</span>
                                        <span class="text-[10px] text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $comment->comment }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 italic">No comments yet.</p>
                        @endforelse
                    </div>

                    <!-- Add Comment -->
                    <form wire:submit.prevent="addComment">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-600 text-white flex items-center justify-center font-bold text-xs">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <div class="flex-1">
                                <textarea wire:model="newComment" rows="2" placeholder="Write a comment..." class="w-full px-3 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white mb-2" required></textarea>
                                <div class="flex justify-end">
                                    <button type="submit" class="px-4 py-1.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm" wire:loading.attr="disabled">
                                        Post Comment
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
         </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="confirmDeleteModal"
         class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800 text-center"
             @click.away="confirmDeleteModal = false">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Delete Task</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Are you sure you want to delete this task? This action cannot be undone.</p>
            <div class="flex items-center justify-center space-x-3">
                <button type="button" @click="confirmDeleteModal = false; taskToDelete = null" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl text-sm font-medium transition-colors">
                    Cancel
                </button>
                <button type="button" @click="$wire.deleteTask(taskToDelete); confirmDeleteModal = false; taskToDelete = null" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-semibold shadow-sm transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
