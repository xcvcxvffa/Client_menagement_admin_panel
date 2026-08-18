<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Content;
use App\Models\Client;
use App\Models\Project;
use App\Models\Retainer;
use App\Models\ContentType;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;

new class extends Component {
    use WithPagination;

    // Filters
    public $search = '';
    public $filterClient = '';
    public $filterPlatform = '';
    public $filterType = '';
    public $filterStatus = '';
    public $filterPriority = '';
    public $filterAssignee = '';

    // Modal State
    public $showModal = false;
    public $isEditing = false;
    public $editId = null;

    // Form Fields
    #[Rule('required|exists:clients,id')]
    public $client_id = '';
    #[Rule('nullable|exists:projects,id')]
    public $project_id = '';
    #[Rule('nullable|exists:retainers,id')]
    public $retainer_id = '';
    #[Rule('required|exists:content_types,id')]
    public $content_type_id = '';
    #[Rule('required|exists:platforms,id')]
    public $platform_id = '';
    #[Rule('required|string|max:255')]
    public $title = '';
    #[Rule('nullable|string')]
    public $description = '';
    #[Rule('nullable|string')]
    public $brief = '';
    #[Rule('nullable|string')]
    public $caption = '';
    #[Rule('nullable|date')]
    public $due_date = '';
    #[Rule('nullable|date')]
    public $publish_date = '';
    #[Rule('required|in:low,medium,high')]
    public $priority = 'medium';
    #[Rule('nullable|exists:users,id')]
    public $assigned_to = '';

    // Dropdown Data Arrays
    public $clients = [];
    public $projects = [];
    public $retainers = [];
    public $contentTypes = [];
    public $platforms = [];
    public $users = [];
    
    // Status color mapping for view
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

    public function mount()
    {
        $businessId = Auth::user()->current_business_id;

        $this->clients = Client::where('business_id', $businessId)->get();
        $this->contentTypes = ContentType::availableToBusiness($businessId)->get();
        $this->platforms = Platform::availableToBusiness($businessId)->get();
        $this->users = User::whereHas('businesses', function($q) use ($businessId) {
            $q->where('businesses.id', $businessId);
        })->get();
        
        $this->updateDynamicDropdowns();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedClientId()
    {
        // Clear dependent fields when client changes
        $this->project_id = '';
        $this->retainer_id = '';
        
        $this->updateDynamicDropdowns();
    }

    public function updatedProjectId()
    {
        if ($this->project_id) {
            $this->retainer_id = '';
        }
    }

    public function updatedRetainerId()
    {
        if ($this->retainer_id) {
            $this->project_id = '';
        }
    }

    public function updateDynamicDropdowns()
    {
        if ($this->client_id) {
            $this->projects = Project::where('client_id', $this->client_id)->get();
            $this->retainers = Retainer::where('client_id', $this->client_id)->get();
        } else {
            $this->projects = [];
            $this->retainers = [];
        }
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->reset([
            'client_id', 'project_id', 'retainer_id',
            'content_type_id', 'platform_id', 'title', 'description', 'brief', 'caption',
            'due_date', 'publish_date', 'priority', 'assigned_to', 'isEditing', 'editId'
        ]);
        $this->priority = 'medium';
        $this->updateDynamicDropdowns();
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $businessId = Auth::user()->current_business_id;

        $data = [
            'business_id' => $businessId,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id ?: null,
            'retainer_id' => $this->retainer_id ?: null,
            'content_type_id' => $this->content_type_id,
            'platform_id' => $this->platform_id,
            'title' => $this->title,
            'description' => $this->description,
            'brief' => $this->brief,
            'caption' => $this->caption,
            'due_date' => $this->due_date ?: null,
            'publish_date' => $this->publish_date ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to ?: null,
        ];

        // Ensure Project & Retainer mutual exclusion is handled here too just in case
        if ($this->project_id && $this->retainer_id) {
            $this->addError('project_id', 'Cannot select both Project and Retainer.');
            return;
        }

        try {
            if ($this->isEditing) {
                $content = Content::findOrFail($this->editId);
                $content->update($data);
                
                \App\Models\ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id' => Auth::id(),
                    'description' => 'Updated content: ' . $content->title,
                    'subject_type' => Content::class,
                    'subject_id' => $content->id,
                ]);
                
            } else {
                $data['status'] = 'idea';
                $data['created_by'] = Auth::id();
                $content = Content::create($data);
                
                \App\Models\ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id' => Auth::id(),
                    'description' => 'Created content: ' . $content->title,
                    'subject_type' => Content::class,
                    'subject_id' => $content->id,
                ]);
            }

            $this->showModal = false;
            session()->flash('message', $this->isEditing ? 'Content updated successfully.' : 'Content created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with()
    {
        $businessId = Auth::user()->current_business_id;

        $query = Content::with(['client', 'contentType', 'platform', 'assignee'])
            ->where('business_id', $businessId);

        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                  ->orWhere('description', 'like', '%'.$this->search.'%')
                  ->orWhere('caption', 'like', '%'.$this->search.'%');
            });
        }
        
        if ($this->filterClient) $query->where('client_id', $this->filterClient);
        if ($this->filterPlatform) $query->where('platform_id', $this->filterPlatform);
        if ($this->filterType) $query->where('content_type_id', $this->filterType);
        if ($this->filterStatus) $query->where('status', $this->filterStatus);
        if ($this->filterPriority) $query->where('priority', $this->filterPriority);
        if ($this->filterAssignee) $query->where('assigned_to', $this->filterAssignee);

        return [
            'contents' => $query->latest()->paginate(15),
            'filterClients' => Client::where('business_id', $businessId)->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Content Calendar & Workflow</h1>
            <p class="text-sm text-gray-500 mt-1">Manage agency content production from idea to publish.</p>
        </div>
        @can('create content')
        <button wire:click="openModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[13px] font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Content
        </button>
        @endcan
    </div>

    <!-- Filters -->
    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-4">
            <div class="lg:col-span-2">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search content..." class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <x-custom-select wire:model.live="filterClient" placeholder="All Clients" class="mt-0"
                :options="collect($filterClients)->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->prepend(['id' => '', 'name' => 'All Clients'])->toArray()" />

            <x-custom-select wire:model.live="filterPlatform" placeholder="All Platforms" class="mt-0"
                :options="collect($platforms)->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->prepend(['id' => '', 'name' => 'All Platforms'])->toArray()" />

            <x-custom-select wire:model.live="filterType" placeholder="All Types" class="mt-0"
                :options="collect($contentTypes)->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->prepend(['id' => '', 'name' => 'All Types'])->toArray()" />

            <x-custom-select wire:model.live="filterStatus" placeholder="All Statuses" class="mt-0"
                :options="collect(array_keys($statusColors))->map(fn($s) => ['id' => $s, 'name' => ucwords(str_replace('_', ' ', $s))])->prepend(['id' => '', 'name' => 'All Statuses'])->toArray()" />

            <x-custom-select wire:model.live="filterAssignee" placeholder="All Assignees" class="mt-0"
                :options="collect($users)->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->prepend(['id' => '', 'name' => 'All Assignees'])->toArray()" />
        </div>
    </div>

    <!-- Messages -->
    @if (session()->has('message'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <!-- Content Table -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Content</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Platform / Type</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Assignee</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Dates</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-[12px] font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($contents as $content)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('contents.show', $content->id) }}" class="text-[14px] font-bold text-gray-900 hover:text-indigo-600 block">{{ $content->title }}</a>
                                <span class="text-[12px] text-gray-500 font-medium px-2 py-0.5 rounded-full {{ $content->priority === 'high' ? 'bg-red-50 text-red-600' : ($content->priority === 'medium' ? 'bg-yellow-50 text-yellow-600' : 'bg-gray-50 text-gray-600') }}">
                                    {{ ucfirst($content->priority) }} Priority
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-[13px] font-medium text-gray-900">{{ $content->client->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span class="text-[11px] font-medium px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md">{{ $content->platform->name ?? 'N/A' }}</span>
                                    <span class="text-[11px] font-medium px-2 py-0.5 bg-purple-50 text-purple-700 rounded-md">{{ $content->contentType->name ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    @if($content->assignee)
                                        <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold" title="{{ $content->assignee->name }}">
                                            {{ substr($content->assignee->name, 0, 1) }}
                                        </div>
                                        <span class="text-[13px] text-gray-700">{{ $content->assignee->name }}</span>
                                    @else
                                        <span class="text-[13px] text-gray-400">Unassigned</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-[12px]">
                                @if($content->due_date)
                                    <div class="text-gray-500">Due: <span class="font-medium text-gray-900">{{ $content->due_date->format('d M y') }}</span></div>
                                @endif
                                @if($content->publish_date)
                                    <div class="text-gray-500">Pub: <span class="font-medium text-indigo-600">{{ $content->publish_date->format('d M y') }}</span></div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-[11px] font-bold rounded-full {{ $statusColors[$content->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucwords(str_replace('_', ' ', $content->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-[13px]">
                                <a href="{{ route('contents.show', $content->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500 text-[14px]">
                                No content found. Adjust your filters or create a new content item.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contents->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $contents->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-900">{{ $isEditing ? 'Edit Content' : 'Create New Content' }}</h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form wire:submit="save" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Basic Info -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Primary Info</h4>
                        
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input wire:model="title" type="text" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                            <x-custom-select wire:model.live="client_id" placeholder="Select Client"
                                :options="collect($clients)->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray()" />
                            @error('client_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>


                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Platform <span class="text-red-500">*</span></label>
                                <x-custom-select wire:model="platform_id" placeholder="Select Platform"
                                    :options="collect($platforms)->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray()" />
                                @error('platform_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Content Type <span class="text-red-500">*</span></label>
                                <x-custom-select wire:model="content_type_id" placeholder="Select Type"
                                    :options="collect($contentTypes)->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->toArray()" />
                                @error('content_type_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Organization & Dates -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Organization</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Project</label>
                                <x-custom-select wire:model.live="project_id" placeholder="None" :disabled="!$client_id || (bool)$retainer_id"
                                    :options="collect($projects)->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->prepend(['id' => '', 'name' => 'None'])->toArray()" />
                            </div>
                            
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Retainer</label>
                                <x-custom-select wire:model.live="retainer_id" placeholder="None" :disabled="!$client_id || (bool)$project_id"
                                    :options="collect($retainers)->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->prepend(['id' => '', 'name' => 'None'])->toArray()" />
                            </div>
                        </div>
                        @error('project_id') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror


                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Due Date</label>
                                <x-date-picker wire:model="due_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                            </div>
                            
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Publish Date</label>
                                <x-date-picker wire:model="publish_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Priority</label>
                                <x-custom-select wire:model="priority" placeholder="Select Priority"
                                    :options="[
                                        ['id' => 'low', 'name' => 'Low'],
                                        ['id' => 'medium', 'name' => 'Medium'],
                                        ['id' => 'high', 'name' => 'High']
                                    ]" />
                            </div>
                            
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Assign To</label>
                                <x-custom-select wire:model="assigned_to" placeholder="Unassigned"
                                    :options="collect($users)->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->prepend(['id' => '', 'name' => 'Unassigned'])->toArray()" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Details -->
                    <div class="md:col-span-2 space-y-4">
                        <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Content Details</h4>
                        
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Description</label>
                            <textarea wire:model="description" rows="2" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="General description..."></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Content Brief</label>
                            <textarea wire:model="brief" rows="3" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Objective, Target Audience, Key Message, CTA..."></textarea>
                        </div>

                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1">Caption / Copy</label>
                            <textarea wire:model="caption" rows="3" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="The actual post caption goes here..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        {{ $isEditing ? 'Save Changes' : 'Create Content' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
