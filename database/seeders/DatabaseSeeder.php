<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;

use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles and Permissions (Spatie)
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage users',
            'manage settings',
            'create clients',
            'edit clients',
            'delete clients',
            'create projects',
            'edit projects',
            'delete projects',
            'create quotes',
            'edit quotes',
            'delete quotes',
            'create invoices',
            'edit invoices',
            'delete invoices',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $ownerRole = Role::firstOrCreate(['name' => 'Owner']);
        $memberRole = Role::firstOrCreate(['name' => 'Member']);

        // Assign all permissions to Owner
        $ownerRole->syncPermissions(Permission::all());

        // Assign limited permissions to Member
        $memberRole->syncPermissions([
            'create payments', 'view payments', 'edit payments', 'delete payments', 'export payments', 'status payments',
            'create retainers', 'view retainers', 'edit retainers', 'delete retainers', 'export retainers', 'status retainers',
            'create expenses', 'view expenses', 'edit expenses', 'delete expenses',
            'manage settings', 'manage team', 'manage users',
        ]);

        // 2. Create Businesses (Tenants)
        $acme = Business::create([
            'name' => 'Acme Agency',
            'slug' => 'acme-agency',
            'currency' => 'INR',
            'branding_color' => '#4F46E5', // Indigo
            'address' => "123, MG Road, Bangalore\nKarnataka, India - 560001",
            'tax_number' => 'GSTIN29AAAAA0000A1Z5',
        ]);

        $pixel = Business::create([
            'name' => 'Pixel Perfect Studios',
            'slug' => 'pixel-perfect',
            'currency' => 'USD',
            'branding_color' => '#EC4899', // Pink
            'address' => "456, Wall Street, New York\nNY, USA - 10005",
            'tax_number' => 'VAT-US999888777',
        ]);

        // 3. Create Users
        $john = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'current_business_id' => $acme->id,
        ]);

        $jane = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'current_business_id' => $pixel->id,
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Miller',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'current_business_id' => $acme->id,
        ]);

        // Assign system-wide roles for Spatie
        $john->assignRole($ownerRole);
        $jane->assignRole($ownerRole);
        $bob->assignRole($memberRole);

        // 4. Associate Users to Businesses via Team Members pivot
        TeamMember::create([
            'business_id' => $acme->id,
            'user_id' => $john->id,
            'role' => 'owner',
        ]);

        TeamMember::create([
            'business_id' => $acme->id,
            'user_id' => $bob->id,
            'role' => 'member',
        ]);

        TeamMember::create([
            'business_id' => $pixel->id,
            'user_id' => $jane->id,
            'role' => 'owner',
        ]);

        TeamMember::create([
            'business_id' => $pixel->id,
            'user_id' => $john->id,
            'role' => 'member', // John is a member in Jane's business
        ]);

        // 5. Seed Data for ACME AGENCY (INR Tenant)
        // Temporarily bypass Global Scope during seeding by specifying business_id explicitly
        // (Or since we aren't logged in, Global Scope getCurrentBusinessId returns null,
        // so we must pass business_id explicitly in creation so the Model event doesn't set it to null)

        $htech = Client::create([
            'business_id' => $acme->id,
            'name' => 'Hindustan Tech',
            'email' => 'contact@hindustantech.com',
            'phone' => '+91 98765 43210',
            'company_name' => 'Hindustan Technologies Pvt Ltd',
            'address' => "Block A, Tech Park, Outer Ring Road, Bangalore, 560103",
            'tax_number' => 'GSTIN29BBBBB1111B1Z2',
            'status' => 'active',
            'currency' => 'INR',
        ]);

        $dstartups = Client::create([
            'business_id' => $acme->id,
            'name' => 'Delhi Startups',
            'email' => 'info@delhistartups.in',
            'phone' => '+91 88888 77777',
            'company_name' => 'Delhi Startups Hub',
            'address' => "Connaught Place, New Delhi, 110001",
            'status' => 'active',
            'currency' => 'INR',
        ]);

        $gretail = Client::create([
            'business_id' => $acme->id,
            'name' => 'Global Retail LLC',
            'email' => 'billing@globalretail.com',
            'phone' => '+1 (555) 019-2834',
            'company_name' => 'Global Retailers LLC',
            'address' => "789 Broadway, Seattle, WA, 98101",
            'status' => 'active',
            'currency' => 'USD', // USD client in INR agency
        ]);

        // CRM Leads
        Lead::create([
            'business_id' => $acme->id,
            'name' => 'Aarav Gupta',
            'email' => 'aarav@alphacorp.co.in',
            'phone' => '+91 99999 88888',
            'company_name' => 'Alpha Corporate Services',
            'value' => 50000.00,
            'status' => 'NEW',
            'notes' => 'Looking for custom Wordpress design and SEO optimization.',
        ]);

        Lead::create([
            'business_id' => $acme->id,
            'name' => 'Samantha Jones',
            'email' => 'sjones@betasoftware.io',
            'phone' => '+1 (555) 098-7654',
            'company_name' => 'Beta Software Systems',
            'value' => 125000.00,
            'status' => 'PROPOSAL_SENT',
            'notes' => 'Sent proposal for Laravel API backend development + Vue dashboard.',
        ]);

        // Projects for Hindustan Tech
        $websiteRedesign = Project::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'name' => 'Corporate Website Redesign',
            'description' => 'Redesign the official marketing website, build standard CMS pages, and integrate contact forms with CRM.',
            'status' => 'active',
            'budget' => 150000.00,
            'started_at' => Carbon::now()->subDays(30),
            'due_at' => Carbon::now()->addDays(30),
        ]);

        $seoOptimization = Project::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'name' => 'SEO & Performance Tuning',
            'description' => 'Optimize site speed, core web vitals, and rank target keywords on page 1 of Google search.',
            'status' => 'planning',
            'budget' => 50000.00,
            'started_at' => Carbon::now()->addDays(10),
            'due_at' => Carbon::now()->addDays(90),
        ]);

        // Tasks for website redesign
        Task::create([
            'project_id' => $websiteRedesign->id,
            'title' => 'Design homepage wireframes and Figma layout',
            'description' => 'Draft the layout for desktop, tablet, and mobile views.',
            'status' => 'done',
            'priority' => 'high',
            'due_date' => Carbon::now()->subDays(20),
            'completed_at' => Carbon::now()->subDays(21),
        ]);

        Task::create([
            'project_id' => $websiteRedesign->id,
            'title' => 'Set up Laravel & Livewire local repository',
            'description' => 'Install packages, setup Breeze auth, and config styling.',
            'status' => 'done',
            'priority' => 'medium',
            'due_date' => Carbon::now()->subDays(15),
            'completed_at' => Carbon::now()->subDays(14),
        ]);

        Task::create([
            'project_id' => $websiteRedesign->id,
            'title' => 'Develop dynamic contact forms with validation',
            'description' => 'Create contact forms and integrate with background mail notifier.',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => Carbon::now()->addDays(5),
        ]);

        Task::create([
            'project_id' => $websiteRedesign->id,
            'title' => 'Deploy draft build to staging server',
            'description' => 'Set up deployment pipeline and check server configuration.',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => Carbon::now()->addDays(15),
        ]);

        // Quotes for clients
        $quote1 = Quote::create([
            'business_id' => $acme->id,
            'client_id' => $dstartups->id,
            'quote_number' => 'QT-2026-001',
            'title' => 'E-Commerce Marketplace MVP',
            'status' => 'sent',
            'subtotal' => 200000.00,
            'tax_total' => 36000.00, // 18% GST
            'discount_total' => 10000.00,
            'total' => 226000.00,
            'notes' => 'Quote is valid for 30 days. Payment terms: 50% advance, 50% upon completion.',
            'valid_until' => Carbon::now()->addDays(15),
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'description' => 'Custom E-commerce backend + admin dashboard build',
            'quantity' => 1,
            'unit_price' => 150000.00,
            'subtotal' => 150000.00,
            'tax' => 18.00,
            'total' => 177000.00,
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'description' => 'Figma UI/UX layouts & client feedback rounds',
            'quantity' => 1,
            'unit_price' => 50000.00,
            'subtotal' => 50000.00,
            'tax' => 18.00,
            'total' => 59000.00,
        ]);

        $quote2 = Quote::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'quote_number' => 'QT-2026-002',
            'title' => 'Website Redesign Agreement',
            'status' => 'accepted',
            'subtotal' => 150000.00,
            'tax_total' => 27000.00,
            'discount_total' => 0.00,
            'total' => 177000.00,
            'notes' => 'Agreed and signed. 50% advance invoice raised.',
            'valid_until' => Carbon::now()->subDays(10),
            'accepted_at' => Carbon::now()->subDays(30),
            'signature_name' => 'Aarav Sharma',
            'signature_date' => Carbon::now()->subDays(30),
        ]);

        QuoteItem::create([
            'quote_id' => $quote2->id,
            'description' => 'Marketing Web Design & Laravel CMS integration',
            'quantity' => 1,
            'unit_price' => 150000.00,
            'subtotal' => 150000.00,
            'tax' => 18.00,
            'total' => 177000.00,
        ]);

        // Invoices
        $invoice1 = Invoice::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'project_id' => $websiteRedesign->id,
            'invoice_number' => 'INV-2026-001',
            'title' => '50% Advance - Website Redesign',
            'status' => 'paid',
            'issue_date' => Carbon::now()->subDays(30),
            'due_date' => Carbon::now()->subDays(15),
            'subtotal' => 75000.00,
            'tax_total' => 13500.00,
            'discount_total' => 0.00,
            'total' => 88500.00,
            'amount_paid' => 88500.00,
            'notes' => 'Thank you for your business!',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice1->id,
            'description' => '50% advance mobilization deposit',
            'quantity' => 1,
            'unit_price' => 75000.00,
            'subtotal' => 75000.00,
            'tax' => 18.00,
            'total' => 88500.00,
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'project_id' => $websiteRedesign->id,
            'invoice_number' => 'INV-2026-002',
            'title' => '25% Milestone 1 - Wireframes Approved',
            'status' => 'sent',
            'issue_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(12),
            'subtotal' => 37500.00,
            'tax_total' => 6750.00,
            'discount_total' => 0.00,
            'total' => 44250.00,
            'amount_paid' => 0.00,
            'notes' => 'Due within 14 days of issue.',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice2->id,
            'description' => '25% project design approval milestone',
            'quantity' => 1,
            'unit_price' => 37500.00,
            'subtotal' => 37500.00,
            'tax' => 18.00,
            'total' => 44250.00,
        ]);

        // Payments
        Payment::create([
            'invoice_id' => $invoice1->id,
            'amount' => 88500.00,
            'paid_at' => Carbon::now()->subDays(28),
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'TXN-BARCLAYS-98124234',
            'notes' => 'Direct deposit confirmed.',
        ]);

        // Meetings
        Meeting::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'title' => 'Project Kickoff & Sitemap Review',
            'description' => 'Align on goals, timelines, sitemap architecture, and copy assets.',
            'starts_at' => Carbon::now()->subDays(30)->setTime(10, 0, 0),
            'ends_at' => Carbon::now()->subDays(30)->setTime(11, 0, 0),
            'location' => 'Google Meet (meet.google.com/abc-defg-hij)',
        ]);

        Meeting::create([
            'business_id' => $acme->id,
            'client_id' => $htech->id,
            'title' => 'Weekly Sync - Development Progress',
            'description' => 'Walkthrough staging site, dynamic components, feedback check.',
            'starts_at' => Carbon::now()->addDays(2)->setTime(15, 30, 0),
            'ends_at' => Carbon::now()->addDays(2)->setTime(16, 0, 0),
            'location' => 'Zoom Link (zoom.us/j/12345678)',
        ]);

        // Activity Logs
        ActivityLog::create([
            'business_id' => $acme->id,
            'user_id' => $john->id,
            'description' => 'Created client Hindustan Tech',
            'subject_id' => $htech->id,
            'subject_type' => Client::class,
        ]);

        ActivityLog::create([
            'business_id' => $acme->id,
            'user_id' => $john->id,
            'description' => 'Accepted quote Website Redesign Agreement',
            'subject_id' => $quote2->id,
            'subject_type' => Quote::class,
        ]);

        // 6. Seed Data for PIXEL PERFECT STUDIOS (USD Tenant)
        $pclient = Client::create([
            'business_id' => $pixel->id,
            'name' => 'Acme Labs',
            'email' => 'admin@acmelabs.io',
            'phone' => '+1 (555) 765-4321',
            'company_name' => 'Acme Laboratories Inc',
            'status' => 'active',
            'currency' => 'USD',
        ]);

        $pproject = Project::create([
            'business_id' => $pixel->id,
            'client_id' => $pclient->id,
            'name' => 'Brand Identity Guide',
            'description' => 'Logo vectors, style guide, color palette, typographies.',
            'status' => 'completed',
            'budget' => 3500.00,
            'started_at' => Carbon::now()->subDays(45),
            'due_at' => Carbon::now()->subDays(15),
        ]);

        Task::create([
            'project_id' => $pproject->id,
            'title' => 'Logo sketching & moodboard',
            'status' => 'done',
            'priority' => 'medium',
            'due_date' => Carbon::now()->subDays(40),
            'completed_at' => Carbon::now()->subDays(40),
        ]);

        Task::create([
            'project_id' => $pproject->id,
            'title' => 'Deliver final vector assets',
            'status' => 'done',
            'priority' => 'high',
            'due_date' => Carbon::now()->subDays(15),
            'completed_at' => Carbon::now()->subDays(15),
        ]);

        $pinvoice = Invoice::create([
            'business_id' => $pixel->id,
            'client_id' => $pclient->id,
            'project_id' => $pproject->id,
            'invoice_number' => 'PP-INV-001',
            'title' => 'Brand Guide Full Payment',
            'status' => 'paid',
            'issue_date' => Carbon::now()->subDays(15),
            'due_date' => Carbon::now()->subDays(1),
            'subtotal' => 3500.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'total' => 3500.00,
            'amount_paid' => 3500.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $pinvoice->id,
            'description' => 'Visual identity system and guidelines package',
            'quantity' => 1,
            'unit_price' => 3500.00,
            'subtotal' => 3500.00,
            'tax' => 0.00,
            'total' => 3500.00,
        ]);

        Payment::create([
            'invoice_id' => $pinvoice->id,
            'amount' => 3500.00,
            'paid_at' => Carbon::now()->subDays(10),
            'payment_method' => 'stripe',
            'transaction_reference' => 'ch_3N82cBLk3n',
        ]);
    }
}
