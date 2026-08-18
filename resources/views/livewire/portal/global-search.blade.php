<?php

use function Livewire\Volt\{state, with};
use App\Models\{Client, Lead, Project, Invoice, Quote, Task};
use Illuminate\Support\Facades\Auth;

state(['search' => '']);

with(function () {
    $businessId = Auth::user()->current_business_id;
    $results = [];

    if (strlen($this->search) >= 2) {
        $term = '%' . $this->search . '%';

        // Clients
        $clients = Client::where('business_id', $businessId)
            ->where(function($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('company_name', 'like', $term);
            })->limit(5)->get();
            
        if ($clients->isNotEmpty()) {
            $results['Clients'] = $clients->map(fn($c) => [
                'title' => $c->name,
                'subtitle' => $c->company_name ?? $c->email,
                'url' => route('clients.show', $c->id),
            ]);
        }

        // Leads
        $leads = Lead::where('business_id', $businessId)
            ->where(function($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('company_name', 'like', $term);
            })->limit(5)->get();
            
        if ($leads->isNotEmpty()) {
            $results['Leads'] = $leads->map(fn($l) => [
                'title' => $l->name,
                'subtitle' => $l->company_name ?? $l->email,
                'url' => route('leads.index') . '?search=' . urlencode($this->search),
            ]);
        }

        // Projects
        $projects = Project::where('business_id', $businessId)
            ->where(function($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('description', 'like', $term);
            })
            ->limit(5)->get();
            
        if ($projects->isNotEmpty()) {
            $results['Projects'] = $projects->map(fn($p) => [
                'title' => $p->name,
                'subtitle' => 'Status: ' . ucfirst($p->status),
                'url' => route('projects.show', $p->id),
            ]);
        }

        // Invoices
        $invoices = Invoice::with('client')->where('business_id', $businessId)
            ->where('invoice_number', 'like', $term)
            ->limit(5)->get();
            
        if ($invoices->isNotEmpty()) {
            $results['Invoices'] = $invoices->map(fn($i) => [
                'title' => 'Invoice ' . $i->invoice_number,
                'subtitle' => optional($i->client)->name ?? 'No Client',
                'url' => route('invoices.show', $i->id),
            ]);
        }

        // Quotes
        $quotes = Quote::with('client')->where('business_id', $businessId)
            ->where(function($q) use ($term) {
                $q->where('quote_number', 'like', $term)
                  ->orWhere('title', 'like', $term);
            })->limit(5)->get();
            
        if ($quotes->isNotEmpty()) {
            $results['Quotes'] = $quotes->map(fn($q) => [
                'title' => 'Quote ' . $q->quote_number,
                'subtitle' => $q->title . ($q->client ? ' • ' . $q->client->name : ''),
                'url' => route('quotes.show', $q->id),
            ]);
        }

        // Tasks
        $tasks = Task::whereHas('project', function($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })
            ->where('title', 'like', $term)
            ->limit(5)->get();
            
        if ($tasks->isNotEmpty()) {
            $results['Tasks'] = $tasks->map(fn($t) => [
                'title' => $t->title,
                'subtitle' => 'Status: ' . ucfirst(str_replace('_', ' ', $t->status)),
                'url' => route('tasks.index'),
            ]);
        }
    }

    return ['results' => $results];
});
?>

<div class="relative w-full max-w-md" x-data="{ open: false }" @click.away="open = false">
    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
        <svg class="w-4.5 h-4.5 text-gray-400" style="width: 1.125rem; height: 1.125rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </span>
    
    <input 
        wire:model.live.debounce.300ms="search"
        @focus="open = true"
        type="text" 
        placeholder="Search everything..." 
        class="w-full pl-10 pr-4 py-2 bg-gray-50 border-transparent rounded-lg text-sm focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" 
    />
    
    <!-- Loading indicator -->
    <div wire:loading wire:target="search" class="absolute right-3 top-2.5">
        <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- Dropdown -->
    <div x-show="open && $wire.search.length >= 2" 
         x-transition
         class="absolute z-50 w-full mt-2 bg-white rounded-xl shadow-xl border border-gray-100 max-h-[80vh] overflow-y-auto"
         style="display: none;">
        
        @if(strlen($search) >= 2)
            @if(empty($results))
                <div class="p-4 text-center text-sm text-gray-500">
                    No results found for "{{ $search }}"
                </div>
            @else
                @foreach($results as $module => $items)
                    <div class="px-4 py-2 bg-gray-50/80 border-y border-gray-100 first:border-t-0">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $module }}</span>
                    </div>
                    <ul class="py-1">
                        @foreach($items as $item)
                            <li>
                                <a href="{{ $item['url'] }}" wire:navigate class="flex flex-col px-4 py-2 hover:bg-gray-50 transition-colors">
                                    <span class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</span>
                                    @if($item['subtitle'])
                                        <span class="text-xs text-gray-500 mt-0.5">{{ $item['subtitle'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @endif
        @endif
    </div>
</div>
