<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\Payment;

use App\Models\ActivityLog;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{with};

$getDashboardData = function () {
    $businessId = Auth::user()?->current_business_id;
    if (!$businessId) {
        return null;
    }

    $business = Business::find($businessId);
    if (!$business) {
        return null;
    }
    
    $currencySymbol = $business->currency === 'USD' ? '$' : '₹';

    // 1. KPI - Monthly Revenue
    $monthlyRevenue = Payment::whereHas('invoice', function ($query) use ($businessId) {
        $query->where('business_id', $businessId);
    })
    ->whereMonth('paid_at', Carbon::now()->month)
    ->whereYear('paid_at', Carbon::now()->year)
    ->sum('amount');

    // 2. KPI - Active Clients
    $activeClientsCount = Client::where('status', 'active')->count();

    // 3. KPI - Active Projects
    $activeProjectsCount = Project::where('status', 'active')->count();

    // 4. KPI - Open Invoices Total
    $openInvoicesTotal = Invoice::whereIn('status', ['sent', 'partially_paid'])
        ->get()
        ->sum(function ($invoice) {
            return $invoice->total - $invoice->amount_paid;
        });

    // 5. Active Projects List
    $activeProjectsList = Project::with('client')
        ->where('status', 'active')
        ->latest()
        ->take(5)
        ->get();

    // 7. Recent Activity Logs
    $recentActivities = ActivityLog::with('user')
        ->latest()
        ->take(5)
        ->get();

    // 8. Chart Data: Last 6 months revenue trend
    $chartLabels = [];
    $chartValues = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = Carbon::now()->subMonths($i);
        $monthName = $date->format('M Y');
        
        $monthRevenue = Payment::whereHas('invoice', function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })
        ->whereMonth('paid_at', $date->month)
        ->whereYear('paid_at', $date->year)
        ->sum('amount');

        $chartLabels[] = $monthName;
        $chartValues[] = (float) $monthRevenue;
    }

    // 9. Invoice Status Breakdown (for Pie Chart)
    $invoiceStatuses = Invoice::where('business_id', $businessId)
        ->selectRaw('status, count(*) as count')
        ->groupBy('status')
        ->get();

    $pieLabels = [];
    $pieValues = [];
    $pieColors = [];

    $statusColors = [
        'draft' => '#9CA3AF', // Gray
        'sent' => '#F59E0B', // Amber
        'paid' => '#10B981', // Emerald
        'partially_paid' => '#3B82F6', // Blue
        'overdue' => '#EF4444', // Red
        'cancelled' => '#6B7280', // Darker Gray
    ];

    foreach ($invoiceStatuses as $statusRow) {
        $pieLabels[] = ucfirst(str_replace('_', ' ', $statusRow->status));
        $pieValues[] = $statusRow->count;
        $pieColors[] = $statusColors[$statusRow->status] ?? '#6366F1';
    }

    // Default if no invoices
    if (empty($pieValues)) {
        $pieLabels = ['No Invoices'];
        $pieValues = [1];
        $pieColors = ['#E5E7EB'];
    }

    return [
        'business' => $business,
        'currencySymbol' => $currencySymbol,
        'monthlyRevenue' => $monthlyRevenue,
        'activeClientsCount' => $activeClientsCount,
        'activeProjectsCount' => $activeProjectsCount,
        'openInvoicesTotal' => $openInvoicesTotal,
        'activeProjectsList' => $activeProjectsList,

        'recentActivities' => $recentActivities,
        'chartLabels' => $chartLabels,
        'chartValues' => $chartValues,
        'pieLabels' => $pieLabels,
        'pieValues' => $pieValues,
        'pieColors' => $pieColors,
    ];
};

with(fn() => [
    'dashboardData' => $getDashboardData()
]);

?>

<div>
    @if(!$dashboardData)
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-100 dark:border-gray-700 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">No active business found</h3>
            <p class="text-sm text-gray-500 mt-2">Create or join a business setting up your workspace.</p>
        </div>
    @else
        <!-- KPIs Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Card 1: Monthly Revenue -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monthly Revenue</span>
                    <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $dashboardData['currencySymbol'] }}{{ number_format($dashboardData['monthlyRevenue'], 2) }}</h3>
                <span class="text-xs text-green-500 font-medium flex items-center mt-2.5">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                    Current month
                </span>
            </div>

            <!-- Card 2: Active Clients -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Clients</span>
                    <div class="p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $dashboardData['activeClientsCount'] }}</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 block mt-2.5">
                    Engaged business entities
                </span>
            </div>

            <!-- Card 3: Active Projects -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Projects</span>
                    <div class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $dashboardData['activeProjectsCount'] }}</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 block mt-2.5">
                    Currently in progress
                </span>
            </div>

            <!-- Card 4: Open Invoices -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Open Invoices</span>
                    <div class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                        <svg class="w-5.5 h-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $dashboardData['currencySymbol'] }}{{ number_format($dashboardData['openInvoicesTotal'], 2) }}</h3>
                <span class="text-xs text-rose-500 font-medium flex items-center mt-2.5">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Awaiting payment
                </span>
            </div>
        </div>

        <!-- Main Dashboard Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Revenue Trend Chart -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Revenue Trend</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Calculated payments for the last 6 months</p>
                    </div>
                </div>
                
                <!-- Chart Container -->
                <div class="h-80 w-full relative" id="chart-parent" wire:ignore>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Right: Invoices by Status (Pie Chart) -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Invoices by Status</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Distribution of all invoices</p>
                    </div>
                </div>
                
                <!-- Pie Chart Container -->
                <div class="h-64 w-full relative" id="pie-chart-parent" wire:ignore>
                    <canvas id="invoiceStatusChart"></canvas>
                </div>
                
                <!-- Legend equivalent via list or allow chart.js to show its legend -->
            </div>
        </div>

        <!-- Lower Grid: Active Projects & Activity Log -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
            
            <!-- Active Projects -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Active Projects</h3>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">View All</a>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($dashboardData['activeProjectsList'] as $project)
                        <div class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between">
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $project->name }}</h4>
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate block mt-0.5">{{ $project->client?->name }}</span>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <span class="text-xs font-bold text-gray-900 dark:text-white">
                                    {{ $dashboardData['currencySymbol'] }}{{ number_format($project->budget, 2) }}
                                </span>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 block mt-0.5">
                                    Due {{ $project->due_at ? $project->due_at->format('M d, Y') : 'No Date' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                            <svg class="w-10 h-10 mx-auto stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <p class="text-xs mt-2.5">No active projects found</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Activity Log -->
            <div class="bg-white dark:bg-gray-850 rounded-2xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Recent Activity</h3>
                </div>

                <div class="flow-root">
                    <ul class="-mb-8">
                        @forelse($dashboardData['recentActivities'] as $activity)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-850" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3.5">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                                                {{ substr($activity->user?->name ?? 'S', 0, 1) }}
                                            </span>
                                        </div>
                                        <div class="flex-grow min-w-0 pt-1.5 flex justify-between space-x-4">
                                            <div>
                                                <p class="text-xs text-gray-700 dark:text-gray-300">
                                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $activity->user?->name }}</span>
                                                    {{ $activity->description }}
                                                </p>
                                            </div>
                                            <div class="text-right text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500">
                                                {{ $activity->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                                <svg class="w-10 h-10 mx-auto stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-xs mt-2.5">No recent logs recorded</p>
                            </div>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- Render Chart Script -->
        <script>
            function initCharts() {
                // Revenue Line Chart
                const revCtx = document.getElementById('revenueChart');
                if (revCtx && typeof Chart !== 'undefined') {
                    let existingRevChart = Chart.getChart(revCtx);
                    if (existingRevChart) {
                        existingRevChart.destroy();
                    }

                    new Chart(revCtx, {
                        type: 'line',
                        data: {
                            labels: {!! json_encode($dashboardData['chartLabels']) !!},
                            datasets: [{
                                label: 'Revenue ({{ $dashboardData['currencySymbol'] }})',
                                data: {!! json_encode($dashboardData['chartValues']) !!},
                                borderColor: '{{ $dashboardData['business']->branding_color ?? "#6366F1" }}',
                                backgroundColor: 'rgba(99, 102, 241, 0.05)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '{{ $dashboardData['business']->branding_color ?? "#6366F1" }}'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    grid: {
                                        color: 'rgba(156, 163, 175, 0.1)'
                                    },
                                    ticks: {
                                        color: '#9CA3AF',
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#9CA3AF',
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Invoice Status Pie Chart
                const pieCtx = document.getElementById('invoiceStatusChart');
                if (pieCtx && typeof Chart !== 'undefined') {
                    let existingPieChart = Chart.getChart(pieCtx);
                    if (existingPieChart) {
                        existingPieChart.destroy();
                    }

                    new Chart(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: {!! json_encode($dashboardData['pieLabels']) !!},
                            datasets: [{
                                data: {!! json_encode($dashboardData['pieValues']) !!},
                                backgroundColor: {!! json_encode($dashboardData['pieColors']) !!},
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20,
                                        font: {
                                            size: 11,
                                            family: "'Plus Jakarta Sans', sans-serif"
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            document.addEventListener('livewire:navigated', () => {
                initCharts();
            });

            // Initial load hook
            window.addEventListener('DOMContentLoaded', () => {
                initCharts();
            });
        </script>
    @endif

</div>
