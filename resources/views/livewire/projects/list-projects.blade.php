<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Client;
use App\Models\TeamMember;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $search = '';

    public $isDrawerOpen = false;
    public $isTrashDrawerOpen = false;
    public $isEditMode = false;
    public $selectedProjectId = null;
    public $viewTab = 'overview';
    public $isClientVisible = true;

    // Confirmation Modal
    public $showConfirmModal = false;
    public $confirmModalAction = ''; // 'delete', 'restore', 'forceDelete'
    public $confirmModalProjectId = null;
    public $confirmModalProjectName = '';

    public $name = '';
    public $client_id = '';
    public $description = '';
    public $status = 'planning';
    public $started_at = '';
    public $due_at = '';
    public $budget = '';
    public $teamMembers = [];

    public $project_type = 'one_off';
    public $billing_day = '';
    public $showNewClientForm = false;
    public $newClientName = '';
    public $newClientEmail = '';
    public $newClientPhone = '';
    public $newClientCurrency = 'INR';

    // Domain & Hosting properties
    public $showDomainHostingForm = false;
    public $domain_name = '';
    public $domain_registrar = '';
    public $domain_cost = '';
    public $domain_purchased_at = '';
    public $domain_expires_at = '';
    public $domain_auto_renew = false;

    public $hosting_provider = '';
    public $hosting_cost = '';
    public $hosting_purchased_at = '';
    public $hosting_expires_at = '';
    public $hosting_auto_renew = false;

    public $domain_hosting_notes = '';

    // For Progress Updates
    public $updateTitle = '';
    public $updateContent = '';
    public $approvalTitle = '';

    // For Financials (Invoices & Payments)
    public $showNewInvoiceModal = false;
    public $invoice_number = '';
    public $invoice_issue_date = '';
    public $invoice_due_date = '';
    public $from_brand_name = '';
    public $from_email = '';
    public $from_phone = '';
    public $from_address = '';
    public $bill_to_name = '';
    public $bill_to_email = '';
    public $bill_to_phone = '';
    public $bill_to_address = '';
    public $invoice_notes = 'Thank you for your business.';
    public $invoiceLineItems = [];
    public $invoice_tax_rate = 0;

    // Edit Invoice Modal
    public $showEditInvoiceModal = false;
    public $editingInvoiceId = null;
    public $editInvoiceStatus = 'draft';
    public $editInvoiceDueDate = '';
    public $editInvoiceNotes = '';

    // For Add Payment Modal
    public $showAddPaymentModal = false;
    public $payment_invoice_id = '';
    public $payment_amount = '';
    public $payment_date = '';
    public $payment_method = 'bank_transfer';
    public $payment_notes = '';

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
            'description' => 'nullable|string',
            'started_at' => 'nullable|date',
            'teamMembers' => 'array',
        ];

        if ($this->project_type === 'retainer') {
            $rules['budget'] = 'required|numeric|min:0';
            $rules['billing_day'] = 'nullable|integer|min:1|max:31';
        } else {
            $rules['status'] = 'required|in:planning,active,completed,on_hold,cancelled';
            $rules['budget'] = 'required|numeric|min:0';
            $rules['due_at'] = 'nullable|date|after_or_equal:started_at';
        }

        return $rules;
    }

    public function with()
    {
        $businessId = Auth::user()->current_business_id;

        $query = Project::where('business_id', $businessId)
            ->with(['client', 'teamMembers.user', 'invoices.payments', 'tasks.assignee']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('client', function ($cq) {
                      $cq->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        $projects = $query->latest()->get();

        $selectedProjectData = $this->selectedProjectId 
            ? Project::where('business_id', $businessId)
                ->with(['client', 'invoices.payments', 'invoices.items', 'teamMembers.user', 'tasks.assignee', 'updates' => function($q) {
                    $q->latest();
                }, 'approvals' => function($q) {
                    $q->latest();
                }])
                ->find($this->selectedProjectId) 
            : null;

        return [
            'projects' => $projects,
            'clients' => Client::where('business_id', $businessId)->orderBy('name')->get(),
            'availableTeamMembers' => TeamMember::where('business_id', $businessId)->with('user')->get(),
            
            'totalProjects' => $projects->count(),
            'ongoingProjects' => $projects->whereIn('status', ['active'])->count(),
            'totalBudget' => $projects->sum('budget'),
            'totalPaid' => $projects->flatMap->invoices->sum('amount_paid'),
            
            'selectedProjectData' => $selectedProjectData,
        ];
    }

    public function createProject()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->isDrawerOpen = true;
    }

    public function editProject()
    {
        $businessId = Auth::user()->current_business_id;
        $project = Project::where('business_id', $businessId)->findOrFail($this->selectedProjectId);

        $this->name = $project->name;
        $this->client_id = $project->client_id;
        $this->description = $project->description;
        $this->status = $project->status;
        $this->started_at = $project->started_at ? $project->started_at->format('Y-m-d') : '';
        $this->due_at = $project->due_at ? $project->due_at->format('Y-m-d') : '';
        $this->budget = $project->budget;
        $this->project_type = $project->is_retainer ? 'retainer' : 'one_off';
        $this->billing_day = $project->billing_cycle ?: '';
        $this->teamMembers = $project->teamMembers->pluck('id')->toArray();

        $this->domain_name = $project->domain_name;
        $this->domain_registrar = $project->domain_registrar;
        $this->domain_cost = $project->domain_cost;
        $this->domain_purchased_at = $project->domain_purchased_at ? $project->domain_purchased_at->format('Y-m-d') : '';
        $this->domain_expires_at = $project->domain_expires_at ? $project->domain_expires_at->format('Y-m-d') : '';
        $this->domain_auto_renew = (bool) $project->domain_auto_renew;
        
        $this->hosting_provider = $project->hosting_provider;
        $this->hosting_cost = $project->hosting_cost;
        $this->hosting_purchased_at = $project->hosting_purchased_at ? $project->hosting_purchased_at->format('Y-m-d') : '';
        $this->hosting_expires_at = $project->hosting_expires_at ? $project->hosting_expires_at->format('Y-m-d') : '';
        $this->hosting_auto_renew = (bool) $project->hosting_auto_renew;

        $this->domain_hosting_notes = $project->domain_hosting_notes;
        $this->showDomainHostingForm = (bool) ($this->domain_name || $this->hosting_provider || $this->domain_cost || $this->hosting_cost);

        $this->isEditMode = true;
    }

    public function viewProject($id)
    {
        $this->resetForm();
        $this->selectedProjectId = $id;
        $this->viewTab = 'overview';
        $this->isEditMode = false;
        $this->isDrawerOpen = true;
    }

    public function closeDrawer()
    {
        $this->isDrawerOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->selectedProjectId = null;
        $this->name = '';
        $this->client_id = '';
        $this->description = '';
        $this->status = 'planning';
        $this->started_at = '';
        $this->due_at = '';
        $this->budget = '';
        $this->teamMembers = [];
        $this->project_type = 'one_off';
        $this->billing_day = '';
        $this->showNewClientForm = false;
        $this->newClientName = '';
        $this->newClientEmail = '';
        $this->newClientPhone = '';
        $this->newClientCurrency = 'INR';

        $this->showDomainHostingForm = false;
        $this->domain_name = '';
        $this->domain_registrar = '';
        $this->domain_cost = '';
        $this->domain_purchased_at = '';
        $this->domain_expires_at = '';
        $this->domain_auto_renew = false;

        $this->hosting_provider = '';
        $this->hosting_cost = '';
        $this->hosting_purchased_at = '';
        $this->hosting_expires_at = '';
        $this->hosting_auto_renew = false;

        $this->domain_hosting_notes = '';
        $this->resetValidation();
    }

    public function toggleNewClientForm()
    {
        $this->showNewClientForm = !$this->showNewClientForm;
    }

    public function toggleDomainHostingForm()
    {
        $this->showDomainHostingForm = !$this->showDomainHostingForm;
    }

    public function addNewClient()
    {
        $this->validate([
            'newClientName' => 'required|string|max:255',
            'newClientEmail' => 'nullable|email|max:255',
            'newClientPhone' => 'nullable|string|max:50',
        ]);

        $businessId = Auth::user()->current_business_id;

        $client = Client::create([
            'business_id' => $businessId,
            'name' => $this->newClientName,
            'email' => $this->newClientEmail ?: null,
            'phone' => $this->newClientPhone ?: null,
            'currency' => $this->newClientCurrency ?: 'INR',
            'status' => 'active',
        ]);

        $this->client_id = $client->id;
        $this->newClientName = '';
        $this->newClientEmail = '';
        $this->newClientPhone = '';
        $this->showNewClientForm = false;

        $this->dispatch('notify', message: 'New client added successfully.', type: 'success');
    }

    public function save()
    {
        $this->validate();
        $businessId = Auth::user()->current_business_id;

        $client = Client::where('business_id', $businessId)->find($this->client_id);
        if (!$client) {
            $this->addError('client_id', 'Invalid client.');
            return;
        }

        $data = [
            'name' => $this->name,
            'client_id' => $this->client_id,
            'description' => $this->description,
            'status' => $this->project_type === 'retainer' ? 'active' : $this->status,
            'started_at' => $this->started_at ?: null,
            'due_at' => $this->project_type === 'one_off' ? ($this->due_at ?: null) : null,
            'budget' => $this->budget ?: 0,
            'is_retainer' => $this->project_type === 'retainer',
            'billing_cycle' => $this->project_type === 'retainer' ? ($this->billing_day ?: null) : null,
            'domain_name' => $this->domain_name ?: null,
            'domain_registrar' => $this->domain_registrar ?: null,
            'domain_cost' => $this->domain_cost ?: null,
            'domain_purchased_at' => $this->domain_purchased_at ?: null,
            'domain_expires_at' => $this->domain_expires_at ?: null,
            'domain_auto_renew' => (bool) $this->domain_auto_renew,
            'hosting_provider' => $this->hosting_provider ?: null,
            'hosting_cost' => $this->hosting_cost ?: null,
            'hosting_purchased_at' => $this->hosting_purchased_at ?: null,
            'hosting_expires_at' => $this->hosting_expires_at ?: null,
            'hosting_auto_renew' => (bool) $this->hosting_auto_renew,
            'domain_hosting_notes' => $this->domain_hosting_notes ?: null,
        ];

        if ($this->isEditMode && $this->selectedProjectId) {
            $project = Project::where('business_id', $businessId)->findOrFail($this->selectedProjectId);
            $project->update($data);
            $project->teamMembers()->sync($this->teamMembers);
            $this->dispatch('notify', message: 'Project updated successfully.', type: 'success');
            $this->isEditMode = false;
        } else {
            $data['business_id'] = $businessId;
            $project = Project::create($data);
            $project->teamMembers()->sync($this->teamMembers);
            $this->dispatch('notify', message: 'Project created successfully.', type: 'success');
        }

        // Create Expenses for Domain and Hosting if costs are entered
        if ($this->domain_cost && (float)$this->domain_cost > 0) {
            Expense::create([
                'business_id' => $businessId,
                'client_id' => $this->client_id,
                'project_id' => $project->id,
                'category' => 'Domain',
                'amount' => $this->domain_cost,
                'date' => $this->domain_purchased_at ?: now()->format('Y-m-d'),
                'description' => 'Domain: ' . ($this->domain_name ?: 'Domain Renewal'),
                'is_recurring' => (bool) $this->domain_auto_renew,
                'next_renewal_date' => $this->domain_expires_at ?: null,
            ]);
        }

        if ($this->hosting_cost && (float)$this->hosting_cost > 0) {
            Expense::create([
                'business_id' => $businessId,
                'client_id' => $this->client_id,
                'project_id' => $project->id,
                'category' => 'Hosting',
                'amount' => $this->hosting_cost,
                'date' => $this->hosting_purchased_at ?: now()->format('Y-m-d'),
                'description' => 'Hosting: ' . ($this->hosting_provider ?: 'Hosting Plan'),
                'is_recurring' => (bool) $this->hosting_auto_renew,
                'next_renewal_date' => $this->hosting_expires_at ?: null,
            ]);
        }

        $this->closeDrawer();
    }

    public function openConfirmModal($action, $id, $name)
    {
        $this->confirmModalAction = $action;
        $this->confirmModalProjectId = $id;
        $this->confirmModalProjectName = $name;
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal()
    {
        $this->showConfirmModal = false;
        $this->confirmModalAction = '';
        $this->confirmModalProjectId = null;
        $this->confirmModalProjectName = '';
    }

    public function executeConfirmAction()
    {
        $action = $this->confirmModalAction;
        $id = $this->confirmModalProjectId;
        $businessId = Auth::user()->current_business_id;

        $this->closeConfirmModal();

        try {
            if ($action === 'delete') {
                $project = Project::where('business_id', $businessId)->find($id);
                if (!$project) {
                    $project = Project::find($id);
                }
                if ($project) {
                    $project->delete();
                    $this->resetForm();
                    $this->isDrawerOpen = false;
                    $this->dispatch('notify', message: 'Project moved to trash successfully.', type: 'success');
                } else {
                    $this->dispatch('notify', message: 'Project not found.', type: 'error');
                }
            } elseif ($action === 'restore') {
                $project = Project::onlyTrashed()->find($id);
                if ($project) {
                    $project->restore();
                    $this->dispatch('notify', message: 'Project restored successfully.', type: 'success');
                } else {
                    $this->dispatch('notify', message: 'Project not found in trash.', type: 'error');
                }
            } elseif ($action === 'forceDelete') {
                $project = Project::onlyTrashed()->find($id);
                if ($project) {
                    $project->forceDelete();
                    $this->dispatch('notify', message: 'Project permanently deleted.', type: 'success');
                } else {
                    $this->dispatch('notify', message: 'Project not found in trash.', type: 'error');
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'An error occurred: ' . $e->getMessage(), type: 'error');
        }
    }

    public function deleteProject($id = null)
    {
        $targetId = $id ?: $this->selectedProjectId;
        $businessId = Auth::user()->current_business_id;

        if ($targetId) {
            $project = Project::where('business_id', $businessId)->find($targetId);
            if (!$project) {
                $project = Project::find($targetId);
            }
            if ($project) {
                $project->delete();
                $this->resetForm();
                $this->isDrawerOpen = false;
                $this->dispatch('notify', message: 'Project moved to trash successfully.', type: 'success');
            } else {
                $this->dispatch('notify', message: 'Project not found.', type: 'error');
            }
        }
    }

    public function confirmDelete($id = null)
    {
        $targetId = $id ?: $this->selectedProjectId;
        if ($targetId) {
            $project = Project::withTrashed()->find($targetId);
            $this->confirmModalAction = 'delete';
            $this->confirmModalProjectId = $targetId;
            $this->confirmModalProjectName = $project?->name ?? 'this project';
            $this->showConfirmModal = true;
        }
    }

    public function confirmRestore($id)
    {
        $project = Project::onlyTrashed()->find($id);
        $this->confirmModalAction = 'restore';
        $this->confirmModalProjectId = $id;
        $this->confirmModalProjectName = $project?->name ?? 'this project';
        $this->showConfirmModal = true;
    }

    public function confirmForceDelete($id)
    {
        $project = Project::onlyTrashed()->find($id);
        $this->confirmModalAction = 'forceDelete';
        $this->confirmModalProjectId = $id;
        $this->confirmModalProjectName = $project?->name ?? 'this project';
        $this->showConfirmModal = true;
    }

    public function openTrash()
    {
        $this->isTrashDrawerOpen = true;
    }

    public function closeTrash()
    {
        $this->isTrashDrawerOpen = false;
    }

    public function updateProjectStatus($projectId, $newStatus)
    {
        $businessId = Auth::user()->current_business_id;
        $project = Project::where('business_id', $businessId)->find($projectId);
        if ($project && in_array($newStatus, ['planning', 'active', 'completed', 'on_hold', 'cancelled'])) {
            $project->update(['status' => $newStatus]);
        }
    }
    
    public function setViewTab($tab)
    {
        $this->viewTab = $tab;
    }
    
    public function toggleClientVisibility()
    {
        $this->isClientVisible = !$this->isClientVisible;
    }
    
    public function addUpdate()
    {
        $this->validate([
            'updateTitle' => 'nullable|string|max:255',
            'updateContent' => 'required|string',
        ]);

        if ($this->selectedProjectId) {
            \App\Models\ProjectUpdate::create([
                'project_id' => $this->selectedProjectId,
                'title' => $this->updateTitle ?: 'Update',
                'content' => $this->updateContent,
                'status' => 'done',
                'is_visible' => true,
            ]);

            $this->updateTitle = '';
            $this->updateContent = '';
            $this->dispatch('notify', message: 'Progress update added.', type: 'success');
        }
    }

    public function toggleUpdateStatus($id)
    {
        $update = \App\Models\ProjectUpdate::find($id);
        if ($update && $update->project_id == $this->selectedProjectId) {
            $update->status = $update->status === 'done' ? 'in_progress' : 'done';
            $update->save();
        }
    }

    public function toggleUpdateVisibility($id)
    {
        $update = \App\Models\ProjectUpdate::find($id);
        if ($update && $update->project_id == $this->selectedProjectId) {
            $update->is_visible = !$update->is_visible;
            $update->save();
        }
    }

    public function deleteUpdate($id)
    {
        $update = \App\Models\ProjectUpdate::find($id);
        if ($update && $update->project_id == $this->selectedProjectId) {
            $update->delete();
            $this->dispatch('notify', message: 'Update removed.', type: 'success');
        }
    }

    public function openNewInvoiceModal()
    {
        if (!$this->selectedProjectId) return;

        $business = Business::find(Auth::user()->current_business_id);
        $project = Project::with('client', 'invoices')->find($this->selectedProjectId);

        $clientCode = 'INV';
        if ($project && $project->name) {
            $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $project->name));
            $clientCode = 'INV-' . (substr($slug, 0, 3) ?: 'PRJ');
        }
        
        $count = $project ? $project->invoices->count() + 1 : 1;
        $this->invoice_number = $clientCode . '-' . date('Ymd') . '-' . $count;
        $this->invoice_issue_date = date('d Aug Y');
        $this->invoice_due_date = date('Y-m-d', strtotime('+8 days'));

        $this->from_brand_name = $business?->name ?? 'Twixel Media pvt ltd';
        $this->from_email = Auth::user()->email ?? 'xyz0449911@gmail.com';
        $this->from_phone = $business?->phone ?? '';
        $this->from_address = $business?->address ?? '';

        $this->bill_to_name = $project?->client?->name ?? 'Super Admin';
        $this->bill_to_email = $project?->client?->email ?? '';
        $this->bill_to_phone = $project?->client?->phone ?? '';
        $this->bill_to_address = $project?->client?->address ?? '';
        $this->invoice_notes = 'Thank you for your business.';
        $this->invoice_tax_rate = 0;

        $this->invoiceLineItems = [
            ['description' => '', 'quantity' => 1, 'unit_price' => 0]
        ];

        $this->showNewInvoiceModal = true;
        $this->dispatch('open-invoice-modal');
    }

    public function closeNewInvoiceModal()
    {
        $this->showNewInvoiceModal = false;
        $this->dispatch('close-invoice-modal');
    }

    public function addInvoiceLineItem()
    {
        $this->invoiceLineItems[] = ['description' => '', 'quantity' => 1, 'unit_price' => 0];
    }

    public function removeInvoiceLineItem($index)
    {
        if (count($this->invoiceLineItems) > 1) {
            unset($this->invoiceLineItems[$index]);
            $this->invoiceLineItems = array_values($this->invoiceLineItems);
        }
    }

    public function createInvoice()
    {
        \Illuminate\Support\Facades\Gate::authorize('create invoices');

        if (empty($this->invoice_number)) {
            $this->dispatch('notify', message: 'Invoice number is required.', type: 'error');
            return;
        }

        if (!$this->selectedProjectId) return;

        $businessId = Auth::user()->current_business_id;
        $project = Project::where('business_id', $businessId)->findOrFail($this->selectedProjectId);

        // Ensure invoice number uniqueness
        if (Invoice::where('invoice_number', $this->invoice_number)->where('business_id', $businessId)->exists()) {
            $this->dispatch('notify', message: 'Invoice number already exists. Please use a unique number.', type: 'error');
            return;
        }

        $subtotal = 0;
        foreach ($this->invoiceLineItems as $item) {
            $qty = floatval($item['quantity'] ?? 1);
            $price = floatval($item['unit_price'] ?? 0);
            $subtotal += $qty * $price;
        }

        $taxRate = floatval($this->invoice_tax_rate ?? 0);
        $taxTotal = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxTotal, 2);

        $issueDateParsed = date('Y-m-d');
        if ($this->invoice_issue_date) {
            $ts = strtotime($this->invoice_issue_date);
            if ($ts !== false) {
                $issueDateParsed = date('Y-m-d', $ts);
            }
        }

        $dueDateParsed = null;
        if ($this->invoice_due_date) {
            $ts = strtotime($this->invoice_due_date);
            if ($ts !== false) {
                $dueDateParsed = date('Y-m-d', $ts);
            }
        }

        $invoice = Invoice::create([
            'business_id'    => $businessId,
            'project_id'     => $project->id,
            'client_id'      => $project->client_id,
            'invoice_number' => $this->invoice_number,
            'title'          => 'Invoice ' . $this->invoice_number,
            'status'         => 'draft',           // Canonical status value
            'issue_date'     => $issueDateParsed,
            'due_date'       => $dueDateParsed,
            'subtotal'       => round($subtotal, 2),
            'tax_rate'       => $taxRate,
            'tax_total'      => $taxTotal,
            'discount_total' => 0,
            'total'          => $total,
            'amount_paid'    => 0,
            'notes'          => $this->invoice_notes ?: 'Thank you for your business.',
        ]);

        foreach ($this->invoiceLineItems as $item) {
            $qty = floatval($item['quantity'] ?? 1);
            $price = floatval($item['unit_price'] ?? 0);
            $lineSubtotal = round($qty * $price, 2);
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'] ?: 'Services',
                'quantity'    => $qty,
                'unit_price'  => $price,
                'subtotal'    => $lineSubtotal,
                'tax'         => $lineTax,
                'total'       => $lineSubtotal + $lineTax,
            ]);
        }

        $this->showNewInvoiceModal = false;
        $this->dispatch('close-invoice-modal');
        $this->dispatch('notify', message: 'Invoice created successfully.', type: 'success');
    }

    public function openAddPaymentModal($invoiceId = null)
    {
        $this->payment_invoice_id = $invoiceId ?: '';
        $this->payment_amount = '';
        $this->payment_date = date('Y-m-d');
        $this->payment_method = 'bank_transfer';
        $this->payment_notes = '';
        $this->showAddPaymentModal = true;
    }

    public function closeAddPaymentModal()
    {
        $this->showAddPaymentModal = false;
        $this->payment_amount = '';
        $this->payment_invoice_id = '';
        $this->payment_notes = '';
    }

    public function savePayment()
    {
        \Illuminate\Support\Facades\Gate::authorize('create payments');

        if (empty($this->payment_amount) || !is_numeric($this->payment_amount) || floatval($this->payment_amount) <= 0) {
            $this->dispatch('notify', message: 'Please enter a valid amount.', type: 'error');
            return;
        }

        if (!$this->selectedProjectId) return;

        $businessId = Auth::user()->current_business_id;
        $project = Project::where('business_id', $businessId)->with('invoices')->findOrFail($this->selectedProjectId);

        $targetInvoice = null;

        if ($this->payment_invoice_id) {
            // Verify the invoice belongs to this project & business
            $targetInvoice = Invoice::where('project_id', $project->id)
                ->where('business_id', $businessId)
                ->find($this->payment_invoice_id);
        } else {
            // Pick first unpaid/partial invoice for this project
            $targetInvoice = $project->invoices->whereNotIn('status', ['paid', 'cancelled'])->first();
        }

        $amount = round(floatval($this->payment_amount), 2);

        if (!$targetInvoice) {
            // Auto-create a simple invoice if none exists
            $count = $project->invoices->count() + 1;
            $slug  = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $project->name), 0, 3));
            $invNumber = 'INV-' . ($slug ?: 'PRJ') . '-' . date('Ymd') . '-' . $count;
            // Ensure uniqueness
            while (Invoice::where('invoice_number', $invNumber)->where('business_id', $businessId)->exists()) {
                $invNumber .= '-' . rand(1, 99);
            }

            $targetInvoice = Invoice::create([
                'business_id'    => $businessId,
                'project_id'     => $project->id,
                'client_id'      => $project->client_id,
                'invoice_number' => $invNumber,
                'title'          => 'Invoice ' . $invNumber,
                'status'         => 'draft',
                'issue_date'     => date('Y-m-d'),
                'due_date'       => date('Y-m-d', strtotime('+8 days')),
                'subtotal'       => $amount,
                'tax_rate'       => 0,
                'tax_total'      => 0,
                'discount_total' => 0,
                'total'          => $amount,
                'amount_paid'    => 0,
                'notes'          => $this->payment_notes ?: 'Thank you for your business.',
            ]);

            InvoiceItem::create([
                'invoice_id'  => $targetInvoice->id,
                'description' => $this->payment_notes ?: 'Project Payment',
                'quantity'    => 1,
                'unit_price'  => $amount,
                'subtotal'    => $amount,
                'tax'         => 0,
                'total'       => $amount,
            ]);
        }

        Payment::create([
            'invoice_id'     => $targetInvoice->id,
            'amount'         => $amount,
            'paid_at'        => $this->payment_date ?: date('Y-m-d'),
            'payment_method' => $this->payment_method ?: 'bank_transfer',
            'notes'          => $this->payment_notes ?: '',
        ]);

        // Recalculate from DB (authoritative total)
        $newAmountPaid = round((float) $targetInvoice->payments()->sum('amount'), 2);
        $invoiceTotal  = round((float) $targetInvoice->total, 2);

        $newStatus = 'partial';
        if ($invoiceTotal > 0 && $newAmountPaid >= $invoiceTotal) {
            $newStatus = 'paid';
        } elseif ($newAmountPaid <= 0) {
            $newStatus = 'sent';
        }

        $targetInvoice->update([
            'amount_paid' => $newAmountPaid,
            'status'      => $newStatus,
        ]);

        $this->payment_amount     = '';
        $this->payment_notes      = '';
        $this->payment_invoice_id = '';
        $this->showAddPaymentModal = false;
        $this->dispatch('notify', message: 'Payment recorded successfully.', type: 'success');
    }

    public function toggleInvoiceStatus($invoiceId)
    {
        $businessId = Auth::user()->current_business_id;
        $invoice = Invoice::where('business_id', $businessId)->find($invoiceId);
        if (!$invoice) return;

        if ($invoice->status === 'paid') {
            // Revert to sent, clear amount_paid only if no real payments exist
            $realPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $invoice->update([
                'status'      => $realPaid > 0 ? 'partial' : 'sent',
                'amount_paid' => $realPaid,
            ]);
            $this->dispatch('notify', message: 'Invoice marked as due.', type: 'success');
        } else {
            // Mark paid — add a payment record for the remaining balance
            $remaining = max(0, round((float)$invoice->total - (float)$invoice->amount_paid, 2));
            if ($remaining > 0) {
                Payment::create([
                    'invoice_id'     => $invoice->id,
                    'amount'         => $remaining,
                    'paid_at'        => date('Y-m-d'),
                    'payment_method' => 'bank_transfer',
                    'notes'          => 'Marked paid from project financials.',
                ]);
            }
            $invoice->update([
                'status'      => 'paid',
                'amount_paid' => (float)$invoice->total,
            ]);
            $this->dispatch('notify', message: 'Invoice marked as paid.', type: 'success');
        }
    }

    public function deleteInvoice($invoiceId)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete invoices');
        $businessId = Auth::user()->current_business_id;
        $invoice = Invoice::where('business_id', $businessId)->find($invoiceId);
        if ($invoice) {
            $invoice->payments()->delete(); // Cascade: remove payments first
            $invoice->items()->delete();    // Cascade: remove line items
            $invoice->delete();
            $this->dispatch('notify', message: 'Invoice deleted.', type: 'success');
        }
    }

    public function openEditInvoiceModal($invoiceId)
    {
        $businessId = Auth::user()->current_business_id;
        $invoice = Invoice::where('business_id', $businessId)->find($invoiceId);
        if (!$invoice) return;

        $this->editingInvoiceId   = $invoice->id;
        $this->editInvoiceStatus  = $invoice->status;
        $this->editInvoiceDueDate = $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '';
        $this->editInvoiceNotes   = $invoice->notes ?? '';
        $this->showEditInvoiceModal = true;
    }

    public function updateInvoice()
    {
        \Illuminate\Support\Facades\Gate::authorize('edit invoices');

        $this->validate([
            'editingInvoiceId'  => 'required|exists:invoices,id',
            'editInvoiceStatus' => 'required|in:draft,sent,partial,paid,cancelled',
            'editInvoiceDueDate'=> 'nullable|date',
            'editInvoiceNotes'  => 'nullable|string|max:2000',
        ]);

        $businessId = Auth::user()->current_business_id;
        $invoice = Invoice::where('business_id', $businessId)->findOrFail($this->editingInvoiceId);

        $invoice->update([
            'status'   => $this->editInvoiceStatus,
            'due_date' => $this->editInvoiceDueDate ?: null,
            'notes'    => $this->editInvoiceNotes,
        ]);

        $this->showEditInvoiceModal = false;
        $this->editingInvoiceId = null;
        $this->dispatch('notify', message: 'Invoice updated successfully.', type: 'success');
    }

    public function deletePaymentItem($paymentId)
    {
        $payment = Payment::find($paymentId);
        if ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();

            if ($invoice) {
                $newPaid = $invoice->payments()->sum('amount');
                $invoice->amount_paid = $newPaid;
                if ($newPaid >= $invoice->total && $invoice->total > 0) {
                    $invoice->status = 'paid';
                } elseif ($newPaid > 0) {
                    $invoice->status = 'partially_paid';
                } else {
                    $invoice->status = 'unpaid';
                }
                $invoice->save();
            }
            $this->dispatch('notify', message: 'Payment deleted.', type: 'success');
        }
    }
};
?>

<div class="h-full flex flex-col bg-white">
    <!-- Header with Badges (Matches Image 2) -->
    <div class="px-8 pt-8 pb-5">
        <div class="flex items-center justify-between mb-6">
            <div class="flex flex-col">
                <h1 class="text-2xl font-bold text-gray-900 leading-tight">Projects</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your client projects and budgets</p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    Total <span class="ml-1.5 font-bold text-gray-900">{{ $totalProjects }}</span>
                </div>
                <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Ongoing <span class="ml-1.5 font-bold text-gray-900">{{ $ongoingProjects }}</span>
                </div>
                <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Budget <span class="ml-1.5 font-bold text-gray-900">₹{{ number_format($totalBudget) }}</span>
                </div>
                <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                    <svg class="w-4 h-4 mr-2 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Paid <span class="ml-1.5 font-bold text-gray-900">₹{{ number_format($totalPaid) }}</span>
                </div>
                <button type="button" class="flex items-center px-3 py-1.5 bg-orange-50 border border-orange-200 rounded-full text-[13px] font-medium text-orange-600 hover:bg-orange-100 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Retainers <span class="ml-1.5 font-bold text-orange-600">0</span> <span class="ml-1 font-normal text-orange-500">· ₹0/mo</span>
                </button>
            </div>
        </div>

        <!-- Search and Actions Bar -->
        <div class="flex items-center w-full">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search projects, clients, or descriptions..." class="block w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-full bg-white text-[13px] font-medium focus:border-orange-500 focus:ring-1 focus:ring-orange-500 shadow-sm placeholder-gray-400 transition-colors">
            </div>
            
            <div class="flex items-center gap-2 ml-4">
                <button wire:click="createProject" class="inline-flex items-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold rounded-full shadow-sm transition-colors">
                    <span class="mr-1.5">+</span> New
                </button>
                <button class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-[13px] font-bold rounded-full shadow-sm hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    CSV
                </button>
                <button class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-[13px] font-bold rounded-full shadow-sm hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    JSON
                </button>
                <button wire:click="openTrash" class="inline-flex items-center px-4 py-2 bg-white border border-rose-200 text-rose-600 text-[13px] font-bold rounded-full shadow-sm hover:bg-rose-50 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Trash
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Message Block Removed for Global Toasts -->

    <!-- Kanban Columns (Matches Image 2) -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-6 px-8 pb-8 overflow-hidden min-h-0">
        
        @php
            $columns = [
                'planning' => ['title' => 'New', 'dot' => 'bg-purple-500', 'status' => 'planning', 'empty' => 'No new projects'],
                'active' => ['title' => 'Ongoing', 'dot' => 'bg-emerald-500', 'status' => 'active', 'empty' => 'No ongoing projects'],
                'completed' => ['title' => 'Completed', 'dot' => 'bg-blue-500', 'status' => 'completed', 'empty' => 'No completed projects']
            ];
        @endphp

        @foreach($columns as $col)
            @php
                $colProjects = $projects->where('status', $col['status']);
                $borderColor = str_replace('bg-', 'border-', $col['dot']);
            @endphp
            <div x-data="{ dragOver: false }" x-on:dragenter.prevent="dragOver = true" x-on:dragleave.prevent="dragOver = false" x-on:drop="dragOver = false; $wire.updateProjectStatus($event.dataTransfer.getData('projectId'), '{{ $col['status'] }}')" x-on:dragover.prevent class="bg-[#f9fafb] rounded-2xl flex flex-col max-h-full overflow-hidden border-2 transition-colors duration-200" :class="dragOver ? '{{ $borderColor }}' : 'border-transparent'">
                <!-- Column Header -->
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full {{ $col['dot'] }}"></div>
                        <h3 class="text-[13px] font-bold text-gray-900">{{ $col['title'] }}</h3>
                        <span class="px-2 py-0.5 bg-white border border-gray-200 text-gray-600 text-[11px] font-bold rounded-full shadow-sm ml-1">{{ $colProjects->count() }}</span>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>
                
                <!-- Column Body -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    @forelse($colProjects as $project)
                        @php
                            $paid = $project->invoices->flatMap->payments->sum('amount') ?? 0;
                            $budget = $project->budget ?: 1;
                            $pct = min(100, round(($paid / $budget) * 100));
                        @endphp
                        
                        <!-- Project Card -->
                        <div draggable="true" x-on:dragstart="$event.dataTransfer.setData('projectId', {{ $project->id }})" wire:key="project-{{ $project->id }}" wire:click="viewProject({{ $project->id }})" class="bg-white rounded-2xl border border-gray-200 p-5 cursor-pointer hover:border-gray-300 hover:shadow-sm transition-all group">
                            
                            <!-- Title & Badge -->
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-base text-gray-900">{{ $project->name }}</h4>
                                @if($project->status === 'active')
                                    <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-600 text-[11px] font-bold rounded-full border border-emerald-100">Ongoing</span>
                                @elseif($project->status === 'planning')
                                    <span class="px-2.5 py-0.5 bg-purple-50 text-purple-600 text-[11px] font-bold rounded-full border border-purple-100">New</span>
                                @elseif($project->status === 'completed')
                                    <span class="px-2.5 py-0.5 bg-blue-50 text-blue-600 text-[11px] font-bold rounded-full border border-blue-100">Completed</span>
                                @endif
                            </div>
                            
                            <!-- Client Avatar & Name -->
                            <div class="flex items-center gap-2 mb-4 text-sm text-gray-600 font-medium">
                                <div class="w-5 h-5 rounded bg-rose-100 flex items-center justify-center text-[10px] font-bold text-rose-600">
                                    {{ substr($project->client?->name ?? 'U', 0, 2) }}
                                </div>
                                {{ $project->client?->name ?? 'Unknown Client' }}
                            </div>

                            @if($project->description)
                                <p class="text-sm text-gray-500 mb-6 line-clamp-1">{{ $project->description }}</p>
                            @else
                                <p class="text-sm text-gray-500 mb-6">{{ $project->id }}</p>
                            @endif
                            
                            <!-- Budget Progress -->
                            <div class="mb-5">
                                <div class="flex justify-between items-end mb-2 text-xs">
                                    <span class="text-gray-400 font-medium">Budget progress</span>
                                    <span class="font-bold text-gray-900">₹{{ number_format($paid) }} <span class="text-gray-400 font-normal">/ ₹{{ number_format($project->budget ?? 0) }}</span></span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="flex items-center justify-between text-xs text-gray-400 font-medium border-t border-gray-50 pt-4">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    {{ $project->due_at ? $project->due_at->format('d/m/Y') : 'No date' }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    {{ $project->teamMembers->count() }} members
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-full min-h-[200px] text-center text-[13px] text-gray-400 font-medium">
                            {{ $col['empty'] }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <!-- Unified Drawer (Create/Edit/View) -->
    <div x-data="{ drawerOpen: @entangle('isDrawerOpen') }" x-show="drawerOpen" x-cloak class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" 
             x-show="drawerOpen" 
             x-transition:enter="ease-in-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in-out duration-300" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0"
             wire:click="closeDrawer"></div>

        <!-- Drawer Panel -->
        <div class="fixed inset-y-0 right-0 flex w-full {{ (!$selectedProjectId || $isEditMode) ? 'max-w-[520px]' : 'max-w-[95vw] lg:max-w-[90vw] xl:max-w-[1300px]' }}">
            <div class="w-full h-full transform transition ease-in-out duration-300 bg-[#fcfcfc] shadow-2xl flex flex-col overflow-hidden"
                 x-show="drawerOpen"
                 x-transition:enter="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                 
                @if(!$selectedProjectId || $isEditMode)
                    <!-- CREATE / EDIT MODE (Matches Image 2 exactly) -->
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-white">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">{{ $isEditMode ? 'Edit Project' : 'Create New Project' }}</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Will be added to New</p>
                        </div>
                        <button wire:click="closeDrawer" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <form wire:submit.prevent="save" class="flex flex-col h-full overflow-hidden">
                        <div class="flex-1 overflow-y-auto p-6 bg-white space-y-6">
                            
                            <div>
                                <label class="block text-[13px] font-bold text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="name" placeholder="e.g., Website Redesign" required class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm p-2.5 placeholder-gray-400">
                            </div>

                            <div>
                                <label class="block text-[13px] font-bold text-gray-700 mb-1">Description</label>
                                <textarea wire:model="description" rows="2" placeholder="Brief description of the project..." class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm p-2.5 placeholder-gray-400"></textarea>
                            </div>

                            <div x-data="{ type: @entangle('project_type').live }">
                                <label class="block text-[13px] font-bold text-gray-700 mb-2">Project Type</label>
                                <div class="grid grid-cols-2 gap-3 mb-6">
                                    <label class="relative flex cursor-pointer rounded-xl border-2 p-3.5 shadow-sm focus:outline-none transition-all duration-150"
                                           :class="type === 'one_off' ? 'bg-orange-50/70 border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200 bg-white hover:border-gray-300'"
                                           @click="type = 'one_off'">
                                        <input type="radio" name="project_type" value="one_off" class="sr-only" x-model="type">
                                        <span class="flex flex-1">
                                            <span class="flex flex-col">
                                                <span class="block text-sm font-bold text-gray-900">One-off Project</span>
                                                <span class="mt-0.5 flex items-center text-[11px] font-medium text-gray-500">Fixed scope & budget</span>
                                            </span>
                                        </span>
                                    </label>
                                    <label class="relative flex cursor-pointer rounded-xl border-2 p-3.5 shadow-sm focus:outline-none transition-all duration-150"
                                           :class="type === 'retainer' ? 'bg-orange-50/70 border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200 bg-white hover:border-gray-300'"
                                           @click="type = 'retainer'">
                                        <input type="radio" name="project_type" value="retainer" class="sr-only" x-model="type">
                                        <span class="flex flex-1">
                                            <span class="flex flex-col">
                                                <span class="block text-sm font-bold text-gray-900">Monthly Retainer</span>
                                                <span class="mt-0.5 flex items-center text-[11px] font-medium text-gray-500">Recurring monthly fee</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                {{-- Client & Status Row --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div :class="type === 'retainer' ? 'col-span-2' : ''">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="block text-[13px] font-bold text-gray-700">Client <span class="text-red-500">*</span></label>
                                            <button type="button" wire:click="toggleNewClientForm" class="text-xs font-bold text-orange-600 hover:text-orange-700 flex items-center transition-colors">
                                                @if($showNewClientForm)
                                                    <span class="mr-1">✕</span> Cancel
                                                @else
                                                    <span class="mr-1">+</span> New client
                                                @endif
                                            </button>
                                        </div>
                                        <div x-data="{ 
                                            open: false, 
                                            value: @entangle('client_id').live,
                                            options: [
                                                { id: '', name: 'Select a client' },
                                                @foreach($clients as $c)
                                                { id: '{{ $c->id }}', name: '{{ addslashes($c->name) }}' },
                                                @endforeach
                                            ],
                                            get selectedName() {
                                                let opt = this.options.find(o => o.id == this.value);
                                                return opt ? opt.name : 'Select a client';
                                            }
                                        }" class="relative text-left w-full mt-1">
                                            {{-- Trigger button --}}
                                            <button type="button" @click="open = !open" @click.outside="open = false"
                                                class="w-full rounded-xl border-2 bg-white text-sm px-4 py-2.5 flex justify-between items-center transition-all duration-150"
                                                :class="open
                                                    ? 'border-black text-gray-900'
                                                    : 'border-gray-200 hover:border-gray-300 text-gray-500'">
                                                <span x-text="selectedName"
                                                      :class="value ? 'text-gray-900 font-medium' : 'text-gray-400 font-normal'"
                                                      class="truncate text-sm leading-5"></span>
                                                {{-- Chevron --}}
                                                <svg class="h-4 w-4 flex-shrink-0 ml-2 transition-transform duration-200"
                                                     :class="open ? 'rotate-180 text-orange-500' : 'text-gray-400'"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            {{-- Dropdown panel --}}
                                            <div x-show="open" x-cloak
                                                 class="absolute z-50 left-0 min-w-full w-max max-w-[280px] mt-1.5 bg-white rounded-2xl border border-gray-100 shadow-xl p-1.5 space-y-0.5"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave-end="opacity-0 translate-y-1 scale-95">
                                                <template x-for="opt in options" :key="opt.id">
                                                    <div @click="value = opt.id; open = false"
                                                         class="flex items-center justify-between px-3.5 py-2 text-sm cursor-pointer select-none rounded-xl transition-colors duration-100"
                                                         :class="value == opt.id
                                                            ? 'bg-orange-50 text-orange-600 font-semibold'
                                                            : 'text-gray-700 font-normal hover:bg-gray-50 hover:text-gray-900'">
                                                        <span x-text="opt.name" class="leading-5 pr-3"></span>
                                                        <svg x-show="value == opt.id"
                                                             class="h-4 w-4 flex-shrink-0 text-orange-500"
                                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        @error('client_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div x-show="type === 'one_off'">
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Status</label>
                                        <div x-data="{ 
                                            open: false, 
                                            value: @entangle('status').live,
                                            options: [
                                                { id: 'planning', name: 'New' },
                                                { id: 'active', name: 'Ongoing' },
                                                { id: 'completed', name: 'Completed' },
                                                { id: 'on_hold', name: 'On Hold' },
                                                { id: 'cancelled', name: 'Cancelled' }
                                            ],
                                            get selectedName() {
                                                let opt = this.options.find(o => o.id == this.value);
                                                return opt ? opt.name : 'New';
                                            }
                                        }" class="relative text-left w-full mt-1">
                                            {{-- Trigger button --}}
                                            <button type="button" @click="open = !open" @click.outside="open = false" 
                                                class="w-full rounded-xl border-2 bg-white text-sm px-4 py-2.5 flex justify-between items-center transition-all duration-150"
                                                :class="open
                                                    ? 'border-black text-gray-900'
                                                    : 'border-gray-200 hover:border-gray-300 text-gray-900 font-medium'">
                                                <span x-text="selectedName" class="truncate text-sm leading-5"></span>
                                                {{-- Chevron --}}
                                                <svg class="h-4 w-4 flex-shrink-0 ml-2 transition-transform duration-200"
                                                     :class="open ? 'rotate-180 text-orange-500' : 'text-gray-400'"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            {{-- Dropdown panel --}}
                                            <div x-show="open" x-cloak
                                                 class="absolute z-50 left-0 min-w-full w-max max-w-[280px] mt-1.5 bg-white rounded-2xl border border-gray-100 shadow-xl p-1.5 space-y-0.5"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave-end="opacity-0 translate-y-1 scale-95">
                                                <template x-for="opt in options" :key="opt.id">
                                                    <div @click="value = opt.id; open = false" 
                                                         class="flex items-center justify-between px-3.5 py-2 text-sm cursor-pointer select-none rounded-xl transition-colors duration-100"
                                                         :class="value == opt.id
                                                            ? 'bg-orange-50 text-orange-600 font-semibold'
                                                            : 'text-gray-700 font-normal hover:bg-gray-50 hover:text-gray-900'">
                                                        <span x-text="opt.name" class="leading-5 pr-3"></span>
                                                        <svg x-show="value == opt.id"
                                                             class="h-4 w-4 flex-shrink-0 text-orange-500"
                                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Add a new client Inline Form --}}
                                @if($showNewClientForm)
                                    <div class="p-5 bg-white border-2 border-orange-200 rounded-2xl shadow-sm space-y-4 my-4">
                                        <h4 class="text-sm font-bold text-gray-800">Add a new client</h4>
                                        
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <input type="text" wire:model="newClientName" placeholder="Client name *" required
                                                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                                @error('newClientName') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <input type="email" wire:model="newClientEmail" placeholder="Email (optional)"
                                                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <input type="text" wire:model="newClientPhone" placeholder="Phone (optional)"
                                                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Invoice Currency</label>
                                            <x-custom-select wire:model="newClientCurrency" placeholder="Select currency"
                                                :options="[
                                                    ['id' => 'INR', 'name' => 'INR — Indian Rupee (₹) · your currency'],
                                                    ['id' => 'USD', 'name' => 'USD — US Dollar ($)'],
                                                    ['id' => 'EUR', 'name' => 'EUR — Euro (€)'],
                                                    ['id' => 'GBP', 'name' => 'GBP — British Pound (£)']
                                                ]" />
                                            <p class="text-xs text-gray-500 mt-1.5">Invoices for this client will be shown in INR, same as the rest of your workspace.</p>
                                        </div>

                                        <div class="flex justify-end gap-2.5 pt-2">
                                            <button type="button" wire:click="toggleNewClientForm"
                                                class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                                Cancel
                                            </button>
                                            <button type="button" wire:click="addNewClient"
                                                class="px-4 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold shadow-sm transition-colors flex items-center">
                                                <span class="mr-1">+</span> Add client
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- One-off Project: Budget Row --}}
                                <div x-show="type === 'one_off'" class="grid grid-cols-2 gap-4 mt-6">
                                    <div>
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Budget * (INR)</label>
                                        <input type="number" wire:model="budget" placeholder="0.00" min="0" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm p-2.5 placeholder-gray-400">
                                        @error('budget') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- Monthly Retainer: Amount & Billing Day Row --}}
                                <div x-show="type === 'retainer'" class="grid grid-cols-2 gap-4 mt-6">
                                    <div>
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Monthly Retainer Amount * (INR)</label>
                                        <input type="number" wire:model="budget" placeholder="0.00" min="0" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm p-2.5 placeholder-gray-400">
                                        @error('budget') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Billing Day * (1–31)</label>
                                        <input type="number" wire:model="billing_day" placeholder="e.g., 1 or 15" min="1" max="31" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm p-2.5 placeholder-gray-400">
                                        @error('billing_day') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- Dates Row --}}
                                <div class="grid grid-cols-2 gap-4 mt-6">
                                    <div>
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Start Date</label>
                                        <x-date-picker wire:model.live="started_at" placeholder="dd-mm-yyyy" />
                                        @error('started_at') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div x-show="type === 'one_off'">
                                        <label class="block text-[13px] font-bold text-gray-700 mb-1">Deadline</label>
                                        <x-date-picker wire:model.live="due_at" placeholder="dd-mm-yyyy" />
                                        @error('due_at') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="text-sm font-bold text-gray-900">Domain & Hosting</h4>
                                        <p class="text-[11px] font-medium text-gray-500">Optional — track renewal dates to get expiry warnings.</p>
                                    </div>
                                    <button type="button" wire:click="toggleDomainHostingForm" class="text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors">
                                        {{ $showDomainHostingForm ? 'Hide' : 'Add' }}
                                    </button>
                                </div>

                                @if($showDomainHostingForm)
                                    <div class="mt-3 space-y-4 pt-1">
                                        {{-- Domain Section --}}
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-3 text-orange-600">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                </svg>
                                                <h5 class="text-sm font-bold text-gray-900">Domain</h5>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Domain name</label>
                                                    <input type="text" wire:model="domain_name" placeholder="example.com"
                                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Registrar</label>
                                                    <input type="text" wire:model="domain_registrar" placeholder="e.g., GoDaddy, Namecheap"
                                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-3 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Cost (₹)</label>
                                                    <input type="number" step="0.01" wire:model="domain_cost" placeholder="0.00"
                                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Purchased on</label>
                                                    <x-date-picker wire:model="domain_purchased_at" placeholder="dd-mm-yyyy" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Expires / renews on</label>
                                                    <x-date-picker wire:model="domain_expires_at" placeholder="dd-mm-yyyy" />
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" id="domain_auto_renew" wire:model="domain_auto_renew" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500 h-4 w-4">
                                                <label for="domain_auto_renew" class="text-xs font-medium text-gray-600">Auto-renew is enabled for the domain</label>
                                            </div>
                                        </div>

                                        <hr class="border-gray-100 my-3">

                                        {{-- Hosting Section --}}
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-3 text-orange-600">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                                </svg>
                                                <h5 class="text-sm font-bold text-gray-900">Hosting</h5>
                                            </div>

                                            <div class="mb-3">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Hosting provider</label>
                                                <input type="text" wire:model="hosting_provider" placeholder="e.g., Hostinger, Vercel, AWS"
                                                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                            </div>

                                            <div class="grid grid-cols-3 gap-3 mb-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Cost (₹)</label>
                                                    <input type="number" step="0.01" wire:model="hosting_cost" placeholder="0.00"
                                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Purchased on</label>
                                                    <x-date-picker wire:model="hosting_purchased_at" placeholder="dd-mm-yyyy" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Expires / renews on</label>
                                                    <x-date-picker wire:model="hosting_expires_at" placeholder="dd-mm-yyyy" />
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" id="hosting_auto_renew" wire:model="hosting_auto_renew" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500 h-4 w-4">
                                                <label for="hosting_auto_renew" class="text-xs font-medium text-gray-600">Auto-renew is enabled for hosting</label>
                                            </div>
                                        </div>

                                        <hr class="border-gray-100 my-3">

                                        {{-- Other details Section --}}
                                        <div>
                                            <label class="block text-sm font-bold text-gray-900 mb-1.5">Other details</label>
                                            <textarea wire:model="domain_hosting_notes" rows="3"
                                                placeholder="Account email, control-panel login, plan tier, nameservers..."
                                                class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-orange-500 focus:ring-orange-500 placeholder-gray-400"></textarea>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                        
                        <div class="p-4 border-t border-gray-100 flex justify-end gap-3 bg-white">
                            <button type="button" wire:click="closeDrawer" class="px-5 py-2.5 rounded-lg border border-gray-200 text-gray-700 text-[13px] font-bold hover:bg-gray-50 transition-colors shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold shadow-sm transition-colors">
                                {{ $isEditMode ? 'Update Project' : 'Create Project' }}
                            </button>
                        </div>
                    </form>
                @else
                    <!-- VIEW MODE (Matches User Images) -->
                    @if($selectedProjectData)
                        @php
                            $vp_paid = $selectedProjectData->invoices->flatMap->payments->sum('amount') ?? 0;
                            $vp_budget = $selectedProjectData->budget ?: 0;
                            $vp_remaining = $vp_budget - $vp_paid;
                            $vp_expenses = \App\Models\Expense::where('project_id', $selectedProjectData->id)->sum('amount') ?? 0;
                            $vp_profit = $vp_budget - $vp_expenses;
                            
                            $sColor = match($selectedProjectData->status) {
                                'planning' => 'bg-emerald-50 text-emerald-600',
                                'active' => 'bg-emerald-50 text-emerald-600',
                                'completed' => 'bg-blue-50 text-blue-600',
                                'on_hold' => 'bg-amber-50 text-amber-600',
                                'cancelled' => 'bg-rose-50 text-rose-600',
                                default => 'bg-gray-50 text-gray-600'
                            };
                            $sLabel = match($selectedProjectData->status) {
                                'planning' => 'Ongoing',
                                'active' => 'Ongoing',
                                'completed' => 'Completed',
                                'on_hold' => 'On Hold',
                                'cancelled' => 'Cancelled',
                                default => 'Unknown'
                            };
                            $pct = $vp_budget > 0 ? min(100, round(($vp_paid / $vp_budget) * 100)) : 0;
                        @endphp
                        
                        <!-- Header & Title -->
                        <div class="px-10 pt-8 pb-5 flex items-start justify-between bg-white">
                            <div>
                                <h2 class="text-[32px] font-bold text-gray-900 mb-3">{{ $selectedProjectData->name }}</h2>
                                <div class="flex items-center gap-3">
                                    <span class="px-3 py-1 rounded-md text-[13px] font-bold {{ $sColor }}">{{ $sLabel }}</span>
                                    <span class="text-[13px] font-medium text-gray-500 flex items-center">
                                        Client: 
                                        <div class="w-4 h-4 ml-2 mr-1 rounded bg-rose-100 flex items-center justify-center text-[8px] font-bold text-rose-600">
                                            {{ substr($selectedProjectData->client?->name ?? 'U', 0, 2) }}
                                        </div>
                                        <a href="#" class="text-gray-900 font-bold hover:underline flex items-center">
                                            {{ $selectedProjectData->client?->name ?? 'None' }} 
                                            <svg class="w-3.5 h-3.5 ml-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </a>
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-confirm-action 
                                    action="deleteProject({{ $selectedProjectData->id }})" 
                                    title="Move to Trash" 
                                    message="Are you sure you want to move this project to trash? This action cannot be undone." 
                                    buttonText="Move to Trash" 
                                    buttonColor="rose">
                                    <x-slot:trigger>
                                        <button type="button"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteProject"
                                            class="px-5 py-2.5 border border-rose-200 text-rose-600 hover:bg-rose-50 text-[13px] font-bold rounded-lg flex items-center transition-colors shadow-sm bg-white disabled:opacity-50 disabled:cursor-not-allowed">
                                            <svg wire:loading wire:target="deleteProject" class="animate-spin -ml-1 mr-2 h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg wire:loading.remove wire:target="deleteProject" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Move to Trash
                                        </button>
                                    </x-slot:trigger>
                                </x-confirm-action>
                                <button wire:click="editProject" class="px-5 py-2.5 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold rounded-lg shadow-sm transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    Edit Project
                                </button>
                                <button wire:click="closeDrawer" class="p-2 ml-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Metric Cards (6 Cards Grid - Image 2) -->
                        <div class="px-10 bg-white grid grid-cols-6 gap-4 py-2">
                            <!-- Budget -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TOTAL BUDGET</p>
                                <p class="text-[22px] font-bold text-gray-900">₹{{ number_format($vp_budget) }}</p>
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-purple-500">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                            </div>
                            <!-- Paid -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TOTAL PAID</p>
                                <p class="text-[22px] font-bold text-emerald-500">₹{{ number_format($vp_paid) }}</p>
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                            </div>
                            <!-- Remaining -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">REMAINING</p>
                                <p class="text-[22px] font-bold text-gray-900">{{ $vp_remaining < 0 ? '-' : '' }}₹{{ number_format(abs($vp_remaining)) }}</p>
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                </div>
                            </div>
                            <!-- Team -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TEAM MEMBERS</p>
                                <p class="text-[22px] font-bold text-gray-900">{{ $selectedProjectData->teamMembers->count() }}</p>
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </div>
                            </div>
                            <!-- Expenses -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TOTAL EX... <span class="text-rose-500 ml-1">↘</span></p>
                                <p class="text-[16px] font-bold text-rose-500">₹{{ number_format($vp_expenses) }}</p>
                            </div>
                            <!-- Profit -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">PROFIT <span class="text-emerald-500 ml-1">↗</span></p>
                                <p class="text-[16px] font-bold text-emerald-500">₹{{ number_format($vp_profit) }}</p>
                            </div>
                        </div>

                        <!-- Tabs Header -->
                        <div class="px-10 border-b border-gray-200 bg-white pt-6">
                            <nav class="-mb-px flex space-x-8 overflow-x-auto">
                                @php
                                    $tabs = [
                                        'overview' => ['icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', 'label' => 'Overview'],
                                        'financials' => ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Payments & Invoices'],
                                        'team' => ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Team'],
                                        'deliverables' => ['icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Deliverables & Files'],
                                        'tasks' => ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'label' => 'Tasks'],
                                        'secrets' => ['icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'label' => 'Secrets'],
                                        'more' => ['icon' => 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z', 'label' => 'More'],
                                    ];
                                @endphp
                                @foreach($tabs as $key => $tab)
                                    <!-- Use wire:click instead of wire:click.prevent and use single quotes inside setViewTab correctly -->
                                    <button wire:click="setViewTab('{{ $key }}')" wire:key="tab-{{ $key }}" class="{{ $viewTab === $key ? 'border-orange-500 text-orange-500 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-semibold' }} flex items-center whitespace-nowrap py-4 px-1 border-b-2 text-[13px] transition-colors focus:outline-none">
                                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" /></svg>
                                        {{ $tab['label'] }}
                                    </button>
                                @endforeach
                            </nav>
                        </div>

                        <!-- Tab Content Wrapper -->
                        <div class="flex-1 overflow-y-auto p-10">
                            
                            @if($viewTab === 'overview')
                                @php
                                    $now = \Carbon\Carbon::now();
                                    $dueDate = $selectedProjectData->due_at;
                                    $startedDate = $selectedProjectData->started_at ?: $selectedProjectData->created_at;

                                    $isOverdue = false;
                                    $formattedDueDate = 'Not set';
                                    $daysText = 'No deadline';
                                    $elapsedPercent = 0;
                                    $statusBadgeText = 'On Track';
                                    $statusNote = 'Set a project deadline to track progress.';

                                    if ($dueDate) {
                                        $formattedDueDate = $dueDate->format('d/m/Y');
                                        if ($now->greaterThan($dueDate)) {
                                            $isOverdue = true;
                                            $diffDays = (int) ceil($now->diffInDays($dueDate));
                                            $daysText = "Overdue by {$diffDays} " . ($diffDays === 1 ? 'day' : 'days');
                                            $elapsedPercent = 100;
                                            $statusBadgeText = 'Overdue';
                                            $statusNote = 'Deadline passed—ship essentials now.';
                                        } else {
                                            $isOverdue = false;
                                            $diffDays = (int) ceil($now->diffInDays($dueDate));
                                            $daysText = "{$diffDays} " . ($diffDays === 1 ? 'day left' : 'days left');
                                            
                                            $totalDuration = max(1, $startedDate->diffInDays($dueDate));
                                            $passedDuration = max(0, $startedDate->diffInDays($now));
                                            $elapsedPercent = min(100, max(0, round(($passedDuration / $totalDuration) * 100)));
                                            
                                            $statusBadgeText = 'On Track';
                                            $statusNote = 'Steady window: keep momentum without scope creep.';
                                        }
                                    }

                                    $budgetProgressPct = $vp_budget > 0 ? min(100, round(($vp_paid / $vp_budget) * 100, 1)) : 0;
                                @endphp

                                <div wire:key="tab-overview-content" class="space-y-6">
                                    <!-- Main Overview Grid (Matches Reference Image 4) -->
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                                        
                                        <!-- Left Column: Progress Section -->
                                        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    Progress
                                                </h3>
                                                @php
                                                    $doneCount = $selectedProjectData->updates ? $selectedProjectData->updates->where('status', 'done')->count() : 0;
                                                    $inProgressCount = $selectedProjectData->updates ? $selectedProjectData->updates->where('status', 'in_progress')->count() : 0;
                                                @endphp
                                                <span class="text-xs text-gray-400 font-medium">
                                                    {{ $doneCount }} done · {{ $inProgressCount }} in progress
                                                </span>
                                            </div>

                                            <!-- Existing Updates List or Subtitle -->
                                            @if($selectedProjectData->updates && $selectedProjectData->updates->count() > 0)
                                                <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1 divide-y divide-gray-50">
                                                    @foreach($selectedProjectData->updates as $update)
                                                        <div class="flex items-start justify-between pt-3 first:pt-0 pb-1 rounded-xl transition-colors hover:bg-gray-50/80 group px-2">
                                                            {{-- Left Icon + Content --}}
                                                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                                                <button type="button" wire:click="toggleUpdateStatus({{ $update->id }})" class="mt-0.5 flex-shrink-0 transition-transform active:scale-95 focus:outline-none">
                                                                    @if($update->status === 'done')
                                                                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                        </svg>
                                                                    @else
                                                                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                        </svg>
                                                                    @endif
                                                                </button>
                                                                <div class="flex-1 min-w-0">
                                                                    <h4 class="text-sm font-semibold text-gray-800 leading-tight truncate">{{ $update->title }}</h4>
                                                                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed whitespace-pre-line">{{ $update->content }}</p>
                                                                </div>
                                                            </div>

                                                            {{-- Right Action Buttons --}}
                                                            <div class="flex items-center gap-2 flex-shrink-0 ml-3 pt-0.5">
                                                                {{-- Eye Icon (Visibility) --}}
                                                                <button type="button" wire:click="toggleUpdateVisibility({{ $update->id }})" 
                                                                        title="{{ $update->is_visible ? 'Visible to client' : 'Hidden from client' }}"
                                                                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100 focus:outline-none">
                                                                    @if($update->is_visible)
                                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                        </svg>
                                                                    @else
                                                                        <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.03 10.03 0 013.982-.863c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-6.046-5.83a3 3 0 114.243 4.243M3 3l18 18" />
                                                                        </svg>
                                                                    @endif
                                                                </button>

                                                                {{-- Refresh / Toggle Status Icon --}}
                                                                <button type="button" wire:click="toggleUpdateStatus({{ $update->id }})"
                                                                        title="Toggle status (Done / In Progress)"
                                                                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100 focus:outline-none">
                                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                    </svg>
                                                                </button>

                                                                {{-- Trash Delete Icon --}}
                                                                <button type="button" wire:click="deleteUpdate({{ $update->id }})"
                                                                        title="Delete update"
                                                                        class="text-rose-400 hover:text-rose-600 transition-colors p-1 rounded-lg hover:bg-rose-50 focus:outline-none">
                                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs text-gray-400 font-normal">No progress updates yet. Add what's currently happening — the client sees this as a timeline.</p>
                                            @endif

                                            <!-- Add Update Form -->
                                            <div class="space-y-3 pt-2 border-t border-gray-100">
                                                <div>
                                                    <input type="text" wire:model="updateTitle" placeholder="Title (e.g. Design phase)" 
                                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 placeholder-gray-400 focus:ring-orange-500 focus:border-orange-500 shadow-sm">
                                                    @error('updateTitle') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <textarea wire:model="updateContent" rows="3" placeholder="What's happening now?" 
                                                              class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 placeholder-gray-400 focus:ring-orange-500 focus:border-orange-500 shadow-sm resize-none"></textarea>
                                                    @error('updateContent') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="flex justify-start">
                                                    <button type="button" wire:click="addUpdate" wire:loading.attr="disabled" wire:target="addUpdate"
                                                            class="px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1.5 disabled:opacity-50">
                                                        <svg wire:loading wire:target="addUpdate" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                        <span wire:loading.remove wire:target="addUpdate" class="text-sm">+</span> Add update
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column: Deadline, Approvals, Change Requests -->
                                        <div class="lg:col-span-1 space-y-6">
                                            
                                            <!-- Deadline Card -->
                                            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                                                <div class="flex justify-between items-center mb-2">
                                                    <span class="text-xs font-medium text-gray-500">Deadline</span>
                                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md {{ $isOverdue ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' }}">
                                                        {{ $statusBadgeText }}
                                                    </span>
                                                </div>
                                                <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $formattedDueDate }}</h3>
                                                
                                                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden mb-2">
                                                    <div class="h-full bg-orange-500 rounded-full transition-all duration-300" style="width: {{ $elapsedPercent }}%"></div>
                                                </div>
                                                <div class="flex justify-between items-center text-[11px] font-medium text-gray-400 mb-3">
                                                    <span>{{ $daysText }}</span>
                                                    <span>{{ $elapsedPercent }}% of time elapsed</span>
                                                </div>
                                                <p class="text-xs text-gray-500 font-normal leading-relaxed">{{ $statusNote }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bottom Section: Budget Progress Card -->
                                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                                        <div class="flex justify-between items-center mb-2.5">
                                            <span class="text-xs font-bold text-gray-700">Budget Progress</span>
                                            <span class="text-xs font-bold text-gray-900">{{ number_format($budgetProgressPct, 1) }}%</span>
                                        </div>
                                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full transition-all duration-300" style="width: {{ min(100, max(0, $budgetProgressPct)) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                            @elseif($viewTab === 'financials')
                                @php
                                    $projectInvoices = $selectedProjectData->invoices ?: collect();
                                    $allPayments = $projectInvoices->flatMap->payments->sortByDesc('created_at');
                                    $totalReceived = $allPayments->sum('amount');
                                @endphp

                                <div wire:key="tab-financials-content" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 items-start">
                                    
                                    <!-- Payments Card (Matches Reference Image 2) -->
                                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5" x-data="{ showPaymentForm: false }">
                                        <div class="flex justify-between items-start">
                                            <h3 class="text-base font-bold text-gray-900">Payments</h3>
                                            <div class="text-right">
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">TOTAL RECEIVED</p>
                                                <p class="text-lg font-bold text-emerald-500">₹{{ number_format($totalReceived, 2) }}</p>
                                            </div>
                                        </div>

                                        <!-- Existing Payments List -->
                                        @if($allPayments->count() > 0)
                                            <div class="space-y-4 divide-y divide-gray-50 max-h-[300px] overflow-y-auto pr-1">
                                                @foreach($allPayments as $pay)
                                                    <div class="pt-3 first:pt-0 space-y-1.5">
                                                        <div class="flex justify-between items-start">
                                                            <div>
                                                                <p class="text-lg font-bold text-gray-900">₹{{ number_format($pay->amount, 2) }}</p>
                                                                <p class="text-xs text-gray-400 font-normal">
                                                                    Invoice {{ $pay->invoice?->invoice_number }}
                                                                </p>
                                                            </div>
                                                            <span class="text-xs text-gray-400 font-medium">
                                                                {{ $pay->paid_at ? \Carbon\Carbon::parse($pay->paid_at)->format('d/m') : $pay->created_at->format('d/m') }}
                                                            </span>
                                                        </div>

                                                        <!-- Payment Actions -->
                                                        <div class="flex items-center gap-3 text-xs font-semibold pt-0.5">
                                                            <a href="#" onclick="alert('Viewing invoice {{ $pay->invoice?->invoice_number }}'); return false;" class="text-orange-600 hover:text-orange-700 flex items-center gap-1 transition-colors">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                                View Invoice
                                                            </a>
                                                            <button type="button" @click="showPaymentForm = true" class="text-gray-500 hover:text-gray-700 flex items-center gap-1 transition-colors">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                Edit
                                                            </button>
                                                            <button type="button" wire:click="deletePaymentItem({{ $pay->id }})" class="text-gray-500 hover:text-rose-600 flex items-center gap-1 transition-colors">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                Delete
                                                            </button>
                                                            <span class="text-gray-500 flex items-center gap-1 cursor-pointer hover:text-gray-700">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                History
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div x-show="!showPaymentForm" class="py-6 text-center">
                                                <p class="text-xs text-gray-400 font-normal">No payments recorded yet</p>
                                            </div>
                                        @endif

                                        <!-- Inline Add Payment Form (Matches Reference Image 2) -->
                                        <div x-show="showPaymentForm" style="display:none;" class="space-y-4 pt-3 border-t border-gray-100">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Amount (₹)</label>
                                                <input type="number" step="0.01" wire:model="payment_amount" placeholder="Enter amount" 
                                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 placeholder-gray-400 focus:ring-orange-500 focus:border-orange-500 shadow-sm">
                                                @error('payment_amount') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Details</label>
                                                <textarea wire:model="payment_notes" rows="3" placeholder="Add a short note (optional)" 
                                                          class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 placeholder-gray-400 focus:ring-orange-500 focus:border-orange-500 shadow-sm resize-none"></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2.5 pt-1">
                                                <button type="button" @click="showPaymentForm = false; $wire.set('payment_amount', ''); $wire.set('payment_notes', '');" 
                                                        class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                                                    Cancel
                                                </button>
                                                <button type="button" wire:click="savePayment" @click="showPaymentForm = false" wire:loading.attr="disabled" 
                                                        class="px-5 py-2 rounded-xl bg-[#ea580c] hover:bg-orange-700 text-white text-xs font-bold shadow-sm transition-colors">
                                                    Add Payment
                                                </button>
                                            </div>
                                        </div>

                                        <!-- + Add Payment Button -->
                                        <button type="button" x-show="!showPaymentForm" @click="showPaymentForm = true" 
                                                class="w-full py-3 bg-[#ea580c] hover:bg-orange-700 text-white text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center justify-center gap-1.5">
                                            <span class="text-sm">+</span> Add Payment
                                        </button>
                                    </div>

                                    <!-- Invoices Card (Matches Reference Image 2) -->
                                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
                                        <div class="flex justify-between items-center">
                                            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Invoices
                                            </h3>
                                            <button type="button" wire:click="openNewInvoiceModal" wire:key="btn-open-new-invoice-modal" class="text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors flex items-center gap-1 focus:outline-none cursor-pointer">
                                                <span class="text-sm">+</span> New Invoice
                                            </button>
                                        </div>

                                        @if($projectInvoices->count() > 0)
                                            <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                                                @foreach($projectInvoices as $inv)
                                                    <div class="p-4 bg-emerald-50/40 border border-emerald-200/80 rounded-2xl space-y-3" x-data="{ expanded: true }">
                                                        <div class="flex justify-between items-start">
                                                            <div>
                                                                <div class="flex items-center gap-2">
                                                                    <h4 class="text-sm font-bold text-gray-900">{{ $inv->invoice_number }}</h4>
                                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                                        {{ ucfirst($inv->status) }}
                                                                    </span>
                                                                </div>
                                                                <p class="text-xs text-gray-500 mt-1 font-medium">
                                                                    Issued {{ $inv->issue_date ? $inv->issue_date->format('d/m/Y') : date('d/m/Y') }} 
                                                                    @if($inv->due_date)
                                                                        • Due {{ $inv->due_date->format('d/m/Y') }}
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            <div class="text-right flex items-center gap-3">
                                                                <div>
                                                                    <p class="text-base font-bold text-gray-900">₹{{ number_format($inv->total, 2) }}</p>
                                                                    <p class="text-[11px] text-gray-400 font-medium">
                                                                        {{ $inv->items ? $inv->items->count() : 1 }} {{ ($inv->items && $inv->items->count() === 1) ? 'item' : 'items' }}
                                                                    </p>
                                                                </div>
                                                                <button type="button" @click="expanded = !expanded" class="text-gray-400 hover:text-gray-600">
                                                                    <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Action Bar (Matches Reference Image 2) -->
                                                        <div x-show="expanded" class="pt-2 border-t border-emerald-200/60 flex items-center justify-between gap-2 text-xs">
                                                            <div class="flex items-center gap-2">
                                                                <a href="{{ route('invoices.pdf', $inv->id) }}" target="_blank" download="Invoice-{{ $inv->invoice_number }}.pdf" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-700 font-bold hover:bg-gray-50 flex items-center gap-1 shadow-sm">
                                                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                                    Download PDF
                                                                </a>
                                                                <button type="button" wire:click="toggleInvoiceStatus({{ $inv->id }})" class="px-3 py-1.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 font-bold hover:bg-amber-100 flex items-center gap-1">
                                                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                                    {{ $inv->status === 'paid' ? 'Mark Due' : 'Mark Paid' }}
                                                                </button>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button type="button" wire:click="openEditInvoiceModal({{ $inv->id }})" wire:key="btn-edit-inv-{{ $inv->id }}" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-white">
                                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                </button>
                                                                <button type="button" wire:click="deleteInvoice({{ $inv->id }})" class="p-1.5 text-rose-400 hover:text-rose-600 rounded-lg hover:bg-white">
                                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="py-10 flex flex-col items-center justify-center text-center space-y-2">
                                                <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p class="text-sm font-bold text-gray-900">No invoices yet</p>
                                                <p class="text-xs text-gray-400 font-normal">Create an invoice to send to your client.</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                            @elseif($viewTab === 'team')
                                <!-- Team Tab (Image 4) -->
                                <div wire:key="tab-team-content" class="space-y-6">
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-500">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-900">Visible to client</h3>
                                            <p class="text-xs text-gray-500 font-medium">Your client can see who is working on this project.</p>
                                        </div>
                                    </div>
                                    <!-- Custom Toggle Switch -->
                                    <button wire:click="toggleClientVisibility" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 {{ $isClientVisible ? 'bg-[#ea580c]' : 'bg-gray-200' }}" role="switch" aria-checked="{{ $isClientVisible ? 'true' : 'false' }}">
                                      <span class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isClientVisible ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                                
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                                    <div class="flex justify-between items-center mb-16">
                                        <h3 class="text-lg font-bold text-gray-900">Team Members</h3>
                                        <a href="#" onclick="return false;" class="text-sm font-bold text-orange-600 hover:text-orange-700 flex items-center">
                                            <span class="mr-1">+</span> Add Member
                                        </a>
                                    </div>
                                    
                                    @if($selectedProjectData->teamMembers->count() > 0)
                                        <ul class="divide-y divide-gray-100 -mt-10">
                                            @foreach($selectedProjectData->teamMembers as $tm)
                                            <li class="py-4 flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    @if($tm->user->avatar_url)
                                                        <img src="{{ $tm->user->avatar_url }}" class="w-10 h-10 rounded-full object-cover shadow-sm">
                                                    @else
                                                        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center font-bold text-orange-700">
                                                            {{ substr($tm->user->name, 0, 2) }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <p class="text-sm font-bold text-gray-900">{{ $tm->user->name }}</p>
                                                        <p class="text-xs text-gray-500">{{ $tm->user->email }}</p>
                                                    </div>
                                                </div>
                                            </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="flex flex-col items-center justify-center text-center pb-8">
                                            <p class="text-sm text-gray-500 font-medium">No team members assigned yet</p>
                                        </div>
                                    @endif
                                </div>
                                </div>

                            @elseif($viewTab === 'tasks')
                                <div wire:key="tab-tasks-content" class="space-y-6">
                                <!-- Tasks Tab (Image 5) -->
                                <div class="flex justify-between items-center mb-6">
                                    <p class="text-[13px] text-gray-500 font-medium">Internal only — never shown to the client.</p>
                                    <button type="button" class="px-5 py-2.5 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold rounded-lg shadow-sm transition-colors flex items-center">
                                        <span class="mr-1.5">+</span> New task
                                    </button>
                                </div>
                                
                                <div class="flex gap-6 overflow-x-auto pb-4 h-full">
                                    @php
                                        $taskCols = [
                                            'todo' => ['title' => 'To do'],
                                            'in_progress' => ['title' => 'In progress'],
                                            'completed' => ['title' => 'Completed']
                                        ];
                                    @endphp
                                    
                                    @foreach($taskCols as $status => $col)
                                        @php
                                            $colTasks = $selectedProjectData->tasks->where('status', $status);
                                        @endphp
                                        <div class="flex-shrink-0 w-[350px] bg-white rounded-2xl border border-gray-100 flex flex-col shadow-sm">
                                            <div class="px-5 py-4 flex items-center gap-2">
                                                <h3 class="text-sm font-bold text-gray-900">{{ $col['title'] }}</h3>
                                                <span class="text-xs text-gray-400 font-bold">({{ $colTasks->count() }})</span>
                                            </div>
                                            <div class="flex-1 p-3 space-y-3">
                                                @forelse($colTasks as $task)
                                                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative @if($status === 'completed') border-l-4 border-l-orange-500 @endif">
                                                        <h4 class="text-[13px] font-bold text-gray-900 mb-1">{{ $task->title }}</h4>
                                                        @if($task->description)
                                                            <p class="text-xs text-gray-500 mb-4">{{ $task->description }}</p>
                                                        @endif
                                                        
                                                        @if($task->assignee)
                                                            <div class="flex items-center gap-2 mb-2">
                                                                <div class="w-4 h-4 rounded-full bg-blue-100 flex items-center justify-center text-[8px] font-bold text-blue-700">
                                                                    {{ substr($task->assignee->name, 0, 2) }}
                                                                </div>
                                                                <span class="text-xs text-gray-500 font-medium">{{ $task->assignee->name }}</span>
                                                            </div>
                                                        @endif
                                                        
                                                        <div class="flex items-center gap-1.5 text-xs text-gray-400 font-medium">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                            Due {{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('d/m/Y') : 'Not set' }}
                                                        </div>
                                                        
                                                        <button class="absolute bottom-4 right-4 text-gray-300 hover:text-rose-500 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </button>
                                                    </div>
                                                @empty
                                                    <div class="text-center py-10 text-xs text-gray-400 font-medium">
                                                        Nothing here
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                </div>
                            
                            @elseif($viewTab === 'deliverables')
                                <div wire:key="tab-deliverables-content" class="space-y-6">
                                <!-- Deliverables Tab -->
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                                    <div class="flex justify-between items-center mb-6">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900">Deliverables & Files</h3>
                                            <p class="text-[13px] text-gray-500 mt-1">Manage project assets, contracts, and deliverables.</p>
                                        </div>
                                        <button type="button" class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            New Folder
                                        </button>
                                    </div>

                                    <!-- Upload Zone -->
                                    <div class="w-full border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 hover:bg-gray-50 hover:border-orange-300 transition-colors cursor-pointer flex flex-col items-center justify-center p-8 mb-8 text-center">
                                        <div class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center mb-3">
                                            <svg class="w-6 h-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-900 mb-1">Click to upload or drag and drop</p>
                                        <p class="text-xs text-gray-500">SVG, PNG, JPG or PDF (max. 10MB)</p>
                                    </div>

                                    <!-- File List -->
                                    <div class="space-y-3">
                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Recent Files</h4>
                                        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-orange-200 hover:shadow-sm transition-all bg-white group cursor-pointer">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 bg-rose-50 rounded-lg flex items-center justify-center text-rose-500">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" opacity=".3"/><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900 group-hover:text-orange-600 transition-colors">Homepage_Design_v2.pdf</p>
                                                    <p class="text-xs text-gray-500">2.4 MB • Uploaded 2 days ago</p>
                                                </div>
                                            </div>
                                            <button type="button" class="text-gray-400 hover:text-gray-900" onclick="event.stopPropagation();">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            @elseif($viewTab === 'secrets')
                                <div wire:key="tab-secrets-content" class="space-y-6">
                                <!-- Secrets Tab -->
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                                    <div class="flex justify-between items-center mb-8">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                Secrets & Credentials
                                            </h3>
                                            <p class="text-[13px] text-gray-500 mt-1">Securely share passwords, API keys, and sensitive data with your team.</p>
                                        </div>
                                        <button type="button" class="px-5 py-2.5 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold rounded-lg shadow-sm transition-colors flex items-center">
                                            <span class="mr-1.5">+</span> Add Secret
                                        </button>
                                    </div>

                                    <div class="space-y-4">
                                        <!-- Secret Item -->
                                        <div class="border border-gray-100 rounded-xl p-5 hover:border-gray-200 hover:shadow-sm transition-all" x-data="{ show: false }">
                                            <div class="flex justify-between items-start mb-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-bold text-gray-900">Production Database</h4>
                                                        <p class="text-[11px] text-gray-400 font-medium">Added by Admin • 1 week ago</p>
                                                    </div>
                                                </div>
                                                <button class="text-xs font-bold text-gray-400 hover:text-orange-600 transition-colors" @click="show = !show" x-text="show ? 'Hide' : 'Reveal'">Reveal</button>
                                            </div>
                                            
                                            <div class="bg-gray-50 rounded-lg p-3 flex justify-between items-center border border-gray-100">
                                                <div class="font-mono text-sm text-gray-700 tracking-wider">
                                                    <span x-show="!show">••••••••••••••••</span>
                                                    <span x-show="show" style="display: none;">db_prod_99x81_secret_key</span>
                                                </div>
                                                <button type="button" onclick="navigator.clipboard.writeText('db_prod_99x81_secret_key');" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-md hover:bg-white transition-colors border border-transparent hover:border-gray-200" title="Copy to clipboard">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- Placeholder for More -->
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ ucfirst($viewTab) }}</h3>
                                    <p class="text-sm text-gray-500">This feature is coming soon.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <!-- </div> -->
<!-- Trash Drawer -->
<div x-data="{ trashOpen: @entangle('isTrashDrawerOpen') }" x-show="trashOpen" x-cloak class="fixed inset-0 z-50 overflow-hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" 
         x-show="trashOpen" 
         x-transition:enter="ease-in-out duration-300" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="ease-in-out duration-300" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0"
         wire:click="closeTrash"></div>

    <div class="fixed inset-y-0 right-0 max-w-md w-full flex">
        <div class="w-full bg-white shadow-2xl flex flex-col transform transition-transform"
             x-show="trashOpen"
             x-transition:enter="transform transition ease-in-out duration-300" 
             x-transition:enter-start="translate-x-full" 
             x-transition:enter-end="translate-x-0" 
             x-transition:leave="transform transition ease-in-out duration-300" 
             x-transition:leave-start="translate-x-0" 
             x-transition:leave-end="translate-x-full">
             
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    <h2 class="text-lg font-bold text-gray-900">Trash</h2>
                </div>
                <button wire:click="closeTrash" class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-4">
                @php
                    $trashedProjects = \App\Models\Project::onlyTrashed()->where('business_id', auth()->user()->current_business_id)->get();
                @endphp

                @forelse($trashedProjects as $tp)
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-sm text-gray-900">{{ $tp->name }}</h4>
                            <span class="text-xs text-gray-400">{{ $tp->deleted_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mb-4 line-clamp-2">{{ $tp->description ?: 'No description' }}</p>
                        
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="confirmRestore({{ $tp->id }})"
                                class="px-3 py-1.5 bg-white border border-emerald-200 text-emerald-700 text-xs font-bold rounded-lg shadow-sm hover:bg-emerald-50 transition-colors flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                Restore
                            </button>
                            <button type="button" wire:click="confirmForceDelete({{ $tp->id }})"
                                class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg hover:bg-rose-100 transition-colors flex items-center border border-rose-200">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                Permanent Delete
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1">Trash is empty</h3>
                        <p class="text-xs text-gray-500">Deleted projects will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

    {{-- Custom Confirmation Modal --}}
    @if($showConfirmModal)
    <div class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
        {{-- Backdrop --}}
        <div wire:click="closeConfirmModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"></div>

        {{-- Card --}}
        <div class="relative bg-white rounded-2xl w-full max-w-[420px] p-6 shadow-2xl border border-gray-100 z-10">

            {{-- Header row --}}
            <div class="flex items-center gap-3 mb-3">
                @if($confirmModalAction === 'restore')
                <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018-8V2M3 10l6 6m-6-6l6-6" /></svg>
                </div>
                <h3 class="text-base font-extrabold text-gray-900">Restore Project?</h3>
                @elseif($confirmModalAction === 'forceDelete')
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </div>
                <h3 class="text-base font-extrabold text-gray-900">Permanently Delete?</h3>
                @else
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </div>
                <h3 class="text-base font-extrabold text-gray-900">Move to Trash?</h3>
                @endif
            </div>

            {{-- Description --}}
            <p class="text-sm text-gray-500 mb-5 leading-relaxed">
                @if($confirmModalAction === 'restore')
                Are you sure you want to restore <span class="font-bold text-gray-800">{{ $confirmModalProjectName }}</span>?
                @elseif($confirmModalAction === 'forceDelete')
                This will <span class="font-semibold text-rose-600">permanently delete</span> <span class="font-bold text-gray-800">{{ $confirmModalProjectName }}</span>. This action cannot be undone.
                @else
                Are you sure you want to move <span class="font-bold text-gray-800">{{ $confirmModalProjectName }}</span> to trash?
                @endif
            </p>

            {{-- Buttons --}}
            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="closeConfirmModal" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-bold text-sm hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                @if($confirmModalAction === 'restore')
                <button type="button" wire:click="executeConfirmAction" wire:loading.attr="disabled" wire:target="executeConfirmAction"
                    class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md shadow-emerald-600/20 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading wire:target="executeConfirmAction" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg wire:loading.remove wire:target="executeConfirmAction" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018-8V2M3 10l6 6m-6-6l6-6" /></svg>
                    Restore Project
                </button>
                @elseif($confirmModalAction === 'forceDelete')
                <button type="button" wire:click="executeConfirmAction" wire:loading.attr="disabled" wire:target="executeConfirmAction"
                    class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-md shadow-rose-600/20 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading wire:target="executeConfirmAction" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg wire:loading.remove wire:target="executeConfirmAction" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    Delete Permanently
                </button>
                @else
                <button type="button" wire:click="executeConfirmAction" wire:loading.attr="disabled" wire:target="executeConfirmAction"
                    class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-md shadow-rose-600/20 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading wire:target="executeConfirmAction" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg wire:loading.remove wire:target="executeConfirmAction" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    Move to Trash
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- New Invoice Modal — always in DOM, Alpine controls visibility -->
    <div
        x-data="{ open: $wire.entangle('showNewInvoiceModal') }"
        x-show="open"
        x-cloak
        @open-invoice-modal.window="open = true"
        @close-invoice-modal.window="open = false"
        wire:key="new-invoice-modal-overlay"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        style="display:none;"
    >
        <!-- Backdrop -->
        <div @click="open = false; $wire.call('closeNewInvoiceModal')" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Card -->
        <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl z-10 flex flex-col max-h-[92vh]">

            <!-- Header -->
            <div class="flex items-start justify-between px-7 pt-6 pb-4 border-b border-gray-100">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">New Invoice</h2>
                    <p class="text-[13px] text-gray-400 mt-0.5">Fill in the details, then save. You can download the PDF afterwards.</p>
                </div>
                <button type="button" @click="open = false; $wire.call('closeNewInvoiceModal')" class="ml-4 p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Scrollable Body -->
            <div class="overflow-y-auto flex-1 px-7 py-5 space-y-5">

                <!-- Row 1: Invoice Number | Issue Date | Due Date -->
                <div class="grid grid-cols-3 gap-4" style="position:relative;">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Invoice Number</label>
                        <input type="text" wire:model="invoice_number"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm">
                    </div>

                    <!-- Issue Date — Flatpickr inside modal -->
                    <div style="position:relative;">
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Issue Date</label>
                        <div
                            x-data="{
                                fp: null,
                                init() {
                                    const inputEl = this.$refs.issueInput;
                                    const initFp = () => {
                                        if (typeof flatpickr !== 'undefined') {
                                            this.fp = flatpickr(inputEl, {
                                                dateFormat: 'j M Y',
                                                defaultDate: ($wire.get ? $wire.get('invoice_issue_date') : $wire.invoice_issue_date) || new Date(),
                                                disableMobile: true,
                                                showMonths: 1,
                                                monthSelectorType: 'static',
                                                position: 'below left',
                                                locale: {
                                                    weekdays: {
                                                        shorthand: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                                                        longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                                                    }
                                                },
                                                onChange: (dates, dateStr) => {
                                                    $wire.set('invoice_issue_date', dateStr);
                                                },
                                                onReady: (selectedDates, dateStr, instance) => {
                                                    if (!instance.calendarContainer.querySelector('.flatpickr-footer-custom')) {
                                                        const footer = document.createElement('div');
                                                        footer.className = 'flatpickr-footer-custom';
                                                        
                                                        const todayBtn = document.createElement('button');
                                                        todayBtn.type = 'button';
                                                        todayBtn.className = 'today-btn-custom';
                                                        todayBtn.innerText = 'Today';
                                                        todayBtn.addEventListener('click', () => {
                                                            instance.setDate(new Date(), true);
                                                            $wire.set('invoice_issue_date', instance.input.value);
                                                            instance.close();
                                                        });
                                                        
                                                        const clearBtn = document.createElement('button');
                                                        clearBtn.type = 'button';
                                                        clearBtn.className = 'clear-btn-custom';
                                                        clearBtn.innerText = 'Clear';
                                                        clearBtn.addEventListener('click', () => {
                                                            instance.clear();
                                                            $wire.set('invoice_issue_date', '');
                                                            instance.close();
                                                        });
                                                        
                                                        footer.appendChild(todayBtn);
                                                        footer.appendChild(clearBtn);
                                                        instance.calendarContainer.appendChild(footer);
                                                    }
                                                }
                                            });
                                        } else {
                                            setTimeout(initFp, 50);
                                        }
                                    };
                                    initFp();
                                }
                            }"
                            x-init="init()"
                            style="position:relative;"
                        >
                            <span class="absolute left-3 top-[11px] text-orange-400 pointer-events-none z-10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <input type="text" x-ref="issueInput" readonly placeholder="Select date"
                                class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-6 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-orange-500 focus:border-orange-500 shadow-sm cursor-pointer">
                        </div>
                    </div>

                    <!-- Due Date — Flatpickr inside modal -->
                    <div style="position:relative;">
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Due Date</label>
                        <div
                            x-data="{
                                fp: null,
                                init() {
                                    const inputEl = this.$refs.dueInput;
                                    const initFp = () => {
                                        if (typeof flatpickr !== 'undefined') {
                                            this.fp = flatpickr(inputEl, {
                                                dateFormat: 'j M Y',
                                                disableMobile: true,
                                                showMonths: 1,
                                                monthSelectorType: 'static',
                                                position: 'below right',
                                                locale: {
                                                    weekdays: {
                                                        shorthand: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                                                        longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                                                    }
                                                },
                                                onChange: (dates, dateStr) => {
                                                    $wire.set('invoice_due_date', dateStr);
                                                },
                                                onReady: (selectedDates, dateStr, instance) => {
                                                    if (!instance.calendarContainer.querySelector('.flatpickr-footer-custom')) {
                                                        const footer = document.createElement('div');
                                                        footer.className = 'flatpickr-footer-custom';
                                                        
                                                        const todayBtn = document.createElement('button');
                                                        todayBtn.type = 'button';
                                                        todayBtn.className = 'today-btn-custom';
                                                        todayBtn.innerText = 'Today';
                                                        todayBtn.addEventListener('click', () => {
                                                            instance.setDate(new Date(), true);
                                                            $wire.set('invoice_due_date', instance.input.value);
                                                            instance.close();
                                                        });
                                                        
                                                        const clearBtn = document.createElement('button');
                                                        clearBtn.type = 'button';
                                                        clearBtn.className = 'clear-btn-custom';
                                                        clearBtn.innerText = 'Clear';
                                                        clearBtn.addEventListener('click', () => {
                                                            instance.clear();
                                                            $wire.set('invoice_due_date', '');
                                                            instance.close();
                                                        });
                                                        
                                                        footer.appendChild(todayBtn);
                                                        footer.appendChild(clearBtn);
                                                        instance.calendarContainer.appendChild(footer);
                                                    }
                                                }
                                            });
                                        } else {
                                            setTimeout(initFp, 50);
                                        }
                                    };
                                    initFp();
                                }
                            }"
                            x-init="init()"
                            style="position:relative;"
                            class="flatpickr-right-align"
                        >
                            <span class="absolute left-3 top-[11px] text-orange-400 pointer-events-none z-10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <input type="text" x-ref="dueInput" readonly placeholder="Select date"
                                class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-orange-500 focus:border-orange-500 shadow-sm cursor-pointer">
                        </div>
                    </div>

                </div>

                <!-- Row 2: From | Bill To -->
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="block text-[12px] font-semibold text-gray-600">From (your brand)</label>
                        <input type="text" wire:model="from_brand_name" placeholder="Brand name"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="email" wire:model="from_email" placeholder="Email"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                            <input type="text" wire:model="from_phone" placeholder="Phone"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                        </div>
                        <textarea wire:model="from_address" rows="3" placeholder="Address (optional)"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm resize-none placeholder-gray-400"></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[12px] font-semibold text-gray-600">Bill To</label>
                        <input type="text" wire:model="bill_to_name" placeholder="Client name"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="email" wire:model="bill_to_email" placeholder="Client email"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                            <input type="text" wire:model="bill_to_phone" placeholder="Client phone"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                        </div>
                        <textarea wire:model="bill_to_address" rows="3" placeholder="Client address (optional)"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm resize-none placeholder-gray-400"></textarea>
                    </div>
                </div>

                <!-- Line Items -->
                <div>
                    <label class="block text-[13px] font-bold text-gray-800 mb-2">Line Items</label>
                    <div class="space-y-2">
                        @foreach($invoiceLineItems as $idx => $item)
                        @php
                            $lineQty  = floatval($item['quantity']  ?? 1);
                            $lineRate = floatval($item['unit_price'] ?? 0);
                            $lineAmt  = $lineQty * $lineRate;
                        @endphp
                        <div class="border border-gray-200 rounded-xl p-3 bg-white shadow-sm space-y-2.5">
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="invoiceLineItems.{{ $idx }}.description" placeholder="Item description"
                                    class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm placeholder-gray-400">
                                <button type="button" wire:click="removeInvoiceLineItem({{ $idx }})" title="Remove"
                                    class="flex-shrink-0 p-1.5 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Qty</label>
                                    <input type="number" wire:model.live="invoiceLineItems.{{ $idx }}.quantity" min="1"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Rate</label>
                                    <input type="number" wire:model.live="invoiceLineItems.{{ $idx }}.unit_price" step="0.01" min="0"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm">
                                </div>
                            </div>
                            <div class="text-right text-[13px] text-gray-400 font-medium">
                                Amount: <span class="font-bold text-gray-800">{{ number_format($lineAmt, 2) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addInvoiceLineItem"
                        class="mt-3 flex items-center gap-1 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add line item
                    </button>
                </div>

                <!-- Notes & Totals -->
                @php
                    $calcSubtotal = 0;
                    foreach($invoiceLineItems as $it) {
                        $q = floatval($it['quantity'] ?? 1);
                        $r = floatval($it['unit_price'] ?? 0);
                        $calcSubtotal += $q * $r;
                    }
                    $calcTaxRate = floatval($invoice_tax_rate ?? 0);
                    $calcTax = $calcSubtotal * ($calcTaxRate / 100);
                    $calcTotal = $calcSubtotal + $calcTax;
                @endphp
                <div class="grid grid-cols-2 gap-5 items-start">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Notes</label>
                        <textarea wire:model="invoice_notes" rows="4"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm resize-none"></textarea>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-[13px] font-bold text-gray-800 mb-3">Totals</p>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>Subtotal</span>
                                <span class="font-medium text-gray-800">{{ number_format($calcSubtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    <span>Tax %:</span>
                                    <input type="number" wire:model.live="invoice_tax_rate" step="0.1" min="0"
                                        class="w-16 rounded-lg border border-gray-200 px-2 py-1 text-xs text-right text-gray-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent shadow-sm">
                                </div>
                                <span class="font-medium text-gray-800">{{ number_format($calcTax, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-gray-900 pt-2 border-t border-gray-200">
                                <span>Total</span>
                                <span>&#8377;{{ number_format($calcTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /scrollable body -->

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-7 py-4 border-t border-gray-100">
                <button type="button"
                    @click="open = false; $wire.call('closeNewInvoiceModal')"
                    class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" wire:click="createInvoice" wire:loading.attr="disabled"
                    class="px-6 py-2.5 rounded-xl bg-[#ea580c] hover:bg-orange-700 text-white text-sm font-bold shadow-sm transition-colors disabled:opacity-60 flex items-center gap-2">
                    <svg wire:loading wire:target="createInvoice" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Create Invoice
                </button>
            </div>

        </div><!-- /modal card -->
    </div><!-- /modal overlay -->


    <!-- Flatpickr — Image 1 design: white header, circular days, orange dot under today, today button, inside modal -->
    <style>
        /* ===== CONTAINER ===== */
        .flatpickr-calendar {
            font-family: inherit !important;
            background: #ffffff !important;
            border-radius: 14px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02) !important;
            border: 1px solid #e2e8f0 !important;
            overflow: hidden !important;
            padding: 0 !important;
            width: 332px !important; /* Natural width (308px) + 24px padding */
            z-index: 99999999 !important; /* Always render on top of modal wrapper */
            box-sizing: border-box !important;
        }
        .flatpickr-calendar::before,
        .flatpickr-calendar::after { display: none !important; }

        /* ===== WHITE HEADER ===== */
        .flatpickr-months {
            background: #ffffff !important;
            align-items: center !important;
            padding: 12px 12px 4px !important;
            border-bottom: none !important;
            box-sizing: border-box !important;
        }
        .flatpickr-month {
            background: transparent !important;
            color: #1e293b !important;
            fill: #1e293b !important;
            height: 36px !important;
        }
        
        /* Month name and year in center */
        .flatpickr-current-month {
            color: #1e293b !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            padding-top: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 4px !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: transparent !important;
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            cursor: pointer;
            pointer-events: none !important;
        }
        .flatpickr-current-month input.cur-year {
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            background: transparent !important;
            pointer-events: none !important;
            width: auto !important;
        }

        /* ===== PREV / NEXT ARROWS ===== */
        .flatpickr-prev-month,
        .flatpickr-next-month {
            color: #64748b !important;
            fill: #64748b !important;
            top: 12px !important;
            padding: 4px 10px !important;
        }
        .flatpickr-prev-month:hover,
        .flatpickr-next-month:hover {
            background: #f1f5f9 !important;
            border-radius: 6px !important;
            color: #0f172a !important;
        }
        .flatpickr-prev-month svg,
        .flatpickr-next-month svg {
            fill: currentColor !important;
            width: 12px !important;
            height: 12px !important;
        }

        /* ===== WEEKDAY HEADERS ===== */
        .flatpickr-weekdays {
            background: #ffffff !important;
            padding: 8px 12px 4px !important; /* Matches day container padding */
            border-bottom: none !important;
            box-sizing: border-box !important;
            width: 100% !important;
        }
        .flatpickr-weekdaycontainer {
            display: flex !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        span.flatpickr-weekday {
            background: transparent !important;
            color: #94a3b8 !important;
            font-weight: 400 !important; /* regular weight */
            font-size: 12px !important;
        }

        /* ===== DAYS ===== */
        .flatpickr-days {
            background: #ffffff !important;
            border: none !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .dayContainer {
            background: #ffffff !important;
            padding: 4px 12px 8px !important; /* Symmetric 12px padding */
            min-width: 100% !important;
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .flatpickr-day {
            border-radius: 50% !important;
            border: none !important;
            color: #334155 !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            height: 36px !important;
            line-height: 36px !important;
            max-width: 36px !important;
            margin: 1px auto !important;
            transition: all 0.15s ease;
        }

        /* ===== TODAY — Normal style (no dot, no outline, no orange box) ===== */
        .flatpickr-day.today {
            position: relative !important;
            background: transparent !important;
            color: #1e293b !important;
            font-weight: 700 !important;
            border: none !important;
        }
        .flatpickr-day.today:hover {
            background: #f8fafc !important;
            color: #ea580c !important;
        }

        /* ===== SELECTED DAY — solid orange filled square/rounded-square with white bold text ===== */
        .flatpickr-day.selected,
        .flatpickr-day.selected:hover,
        .flatpickr-day.selected:focus {
            background: #ea580c !important;
            border-radius: 8px !important; /* rounded-square */
            color: #ffffff !important;
            font-weight: 700 !important;
            border: none !important;
        }

        /* Today selected: should get the solid orange box like other selected days */
        .flatpickr-day.today.selected,
        .flatpickr-day.today.selected:hover,
        .flatpickr-day.today.selected:focus {
            background: #ea580c !important;
            border-radius: 8px !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            border: none !important;
        }

        /* ===== PREV / NEXT MONTH DAYS (greyed out, non-clickable) ===== */
        .flatpickr-day.prevMonthDay,
        .flatpickr-day.nextMonthDay {
            color: #cbd5e1 !important;
            background: transparent !important;
            pointer-events: none !important;
            cursor: default !important;
        }

        /* ===== DISABLED ===== */
        .flatpickr-day.disabled,
        .flatpickr-day.disabled:hover {
            color: #f1f5f9 !important;
            background: transparent !important;
        }

        /* ===== CUSTOM FOOTER WITH TODAY & CLEAR LINKS ===== */
        .flatpickr-footer-custom {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 12px 12px !important;
            border-top: 1px solid #f1f5f9 !important;
            background: #ffffff !important;
            box-sizing: border-box !important;
            width: 100% !important;
        }
        .today-btn-custom {
            color: #ea580c !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            padding: 0 !important;
            transition: color 0.15s ease;
        }
        .today-btn-custom:hover {
            color: #c2410c !important;
        }
        .clear-btn-custom {
            color: #94a3b8 !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            padding: 0 !important;
            transition: color 0.15s ease;
        }
        .clear-btn-custom:hover {
            color: #475569 !important;
        }
    </style>
</div>
