@props([
    'type' => 'table',
    'count' => 6,
    'rows' => 5,
    'cols' => 4,
])

<div {{ $attributes->merge(['class' => 'w-full animate-pulse']) }}>
    @if ($type === 'stats')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            @for ($i = 0; $i < 4; $i++)
                <div class="bg-white rounded-xl border border-gray-100 p-5 space-y-3">
                    <div class="h-3 bg-gray-200 rounded w-24"></div>
                    <div class="h-7 bg-gray-300 rounded w-16"></div>
                </div>
            @endfor
        </div>

    @elseif ($type === 'dashboard')
        <!-- KPIs Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            @for ($i = 0; $i < 4; $i++)
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="h-3 bg-gray-200 rounded w-28"></div>
                        <div class="w-10 h-10 rounded-xl bg-gray-100"></div>
                    </div>
                    <div class="h-8 bg-gray-300 rounded w-20"></div>
                    <div class="h-3 bg-gray-200 rounded w-24"></div>
                </div>
            @endfor
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Chart Skeleton -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <div class="h-4 bg-gray-300 rounded w-32"></div>
                        <div class="flex gap-2">
                            <div class="h-8 bg-gray-100 rounded w-16"></div>
                            <div class="h-8 bg-gray-100 rounded w-16"></div>
                        </div>
                    </div>
                    <div class="h-64 bg-gray-50 rounded-xl flex items-end p-4 gap-4">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="flex-1 bg-gray-200 rounded-t" style="height: {{ rand(30, 90) }}%"></div>
                        @endfor
                    </div>
                </div>

                <!-- Active Projects Skeleton -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="h-4 bg-gray-300 rounded w-40"></div>
                        <div class="h-4 bg-gray-200 rounded w-16"></div>
                    </div>
                    <div class="space-y-4">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gray-200"></div>
                                    <div class="space-y-2">
                                        <div class="h-3 bg-gray-300 rounded w-32"></div>
                                        <div class="h-3 bg-gray-200 rounded w-20"></div>
                                    </div>
                                </div>
                                <div class="h-6 bg-gray-100 rounded-full w-16"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>

            <!-- Right 1 Col -->
            <div class="space-y-6">
                <!-- Mini Chart/Status -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
                    <div class="h-4 bg-gray-300 rounded w-36"></div>
                    <div class="h-40 bg-gray-50 rounded-xl flex items-center justify-center">
                        <div class="w-24 h-24 rounded-full border-8 border-gray-200"></div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="h-4 bg-gray-300 rounded w-36 mb-4"></div>
                    <div class="space-y-4">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-3 bg-gray-200 rounded w-full"></div>
                                    <div class="h-2.5 bg-gray-200 rounded w-20"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

    @elseif ($type === 'clients')
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @for ($i = 0; $i < $count; $i++)
                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5 space-y-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gray-200"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-gray-300 rounded w-28"></div>
                                <div class="h-3 bg-gray-200 rounded w-20"></div>
                            </div>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-gray-100"></div>
                    </div>
                    <div class="h-10 bg-gray-50 rounded-lg"></div>
                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                        <div class="h-3 bg-gray-200 rounded w-24"></div>
                        <div class="h-3 bg-gray-300 rounded w-16"></div>
                    </div>
                </div>
            @endfor
        </div>

    @elseif ($type === 'projects')
        <!-- Kanban Board Skeleton -->
        <div class="flex gap-6 overflow-x-auto pb-4 scrollbar-hide h-[calc(100vh-250px)]">
            @for ($c = 0; $c < 4; $c++)
                <div class="flex-1 min-w-[280px] bg-gray-50 rounded-2xl p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="h-4 bg-gray-200 rounded w-24"></div>
                        <div class="w-6 h-6 rounded bg-gray-100"></div>
                    </div>
                    <div class="space-y-3">
                        @for ($i = 0; $i < 2; $i++)
                            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4 shadow-sm">
                                <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                    <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                                    <div class="h-3 bg-gray-200 rounded w-16"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>

    @elseif ($type === 'tasks')
        <!-- Kanban Board Skeleton for Tasks -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            @for ($c = 0; $c < 4; $c++)
                <div class="bg-[#f9fafb] rounded-2xl p-4 space-y-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="h-4 bg-gray-200 rounded w-24"></div>
                        <div class="h-4 bg-gray-200 rounded w-8"></div>
                    </div>
                    <div class="space-y-3">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3 shadow-sm">
                                <div class="h-4 bg-gray-300 rounded w-11/12"></div>
                                <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                                <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                    <div class="w-6 h-6 rounded-full bg-gray-200"></div>
                                    <div class="h-3 bg-gray-200 rounded w-12"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>

    @elseif ($type === 'file-manager')
        <!-- Folders Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            @for ($i = 0; $i < 4; $i++)
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                    <div class="w-10 h-10 rounded-lg bg-gray-200"></div>
                    <div class="h-4 bg-gray-300 rounded w-24"></div>
                    <div class="h-3 bg-gray-200 rounded w-16"></div>
                </div>
            @endfor
        </div>
        <!-- Files Table Skeleton -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="h-4 bg-gray-200 rounded w-32"></div>
            </div>
            <div class="p-6 space-y-4">
                @for ($i = 0; $i < 5; $i++)
                    <div class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded bg-gray-200"></div>
                            <div class="space-y-2">
                                <div class="h-3 bg-gray-300 rounded w-36"></div>
                                <div class="h-2.5 bg-gray-200 rounded w-20"></div>
                            </div>
                        </div>
                        <div class="h-3 bg-gray-200 rounded w-16"></div>
                    </div>
                @endfor
            </div>
        </div>

    @elseif ($type === 'messages')
        <div class="bg-white rounded-2xl border border-gray-150 shadow-sm flex h-[calc(100vh-170px)] overflow-hidden">
            <!-- User List -->
            <div class="w-80 border-r border-gray-150 flex flex-col flex-shrink-0">
                <div class="p-4 border-b border-gray-100 space-y-3">
                    <div class="h-8 bg-gray-100 rounded-lg"></div>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-3">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="flex items-center gap-3 p-2 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-gray-200"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3.5 bg-gray-300 rounded w-24"></div>
                                <div class="h-3 bg-gray-200 rounded w-36"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            <!-- Message Area -->
            <div class="flex-1 flex flex-col bg-[#f9fafb]">
                <div class="bg-white px-6 py-4 border-b border-gray-150 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-200"></div>
                        <div class="space-y-1.5">
                            <div class="h-3.5 bg-gray-300 rounded w-24"></div>
                            <div class="h-2.5 bg-gray-200 rounded w-16"></div>
                        </div>
                    </div>
                </div>
                <div class="flex-1 p-6 space-y-6 overflow-y-auto">
                    <div class="flex gap-3 max-w-md">
                        <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                        <div class="bg-white rounded-2xl p-4 shadow-sm flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-full"></div>
                            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </div>
                    <div class="flex gap-3 max-w-md ml-auto justify-end">
                        <div class="bg-orange-100/60 rounded-2xl p-4 shadow-sm flex-1 space-y-2 text-right">
                            <div class="h-3 bg-gray-200 rounded w-full ml-auto"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2 ml-auto"></div>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                    </div>
                </div>
                <div class="p-4 bg-white border-t border-gray-150">
                    <div class="h-10 bg-gray-50 rounded-xl"></div>
                </div>
            </div>
        </div>

    @elseif ($type === 'profile')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
                    <div class="w-24 h-24 rounded-full bg-gray-200 mx-auto"></div>
                    <div class="h-4 bg-gray-300 rounded w-32 mx-auto"></div>
                    <div class="h-3 bg-gray-200 rounded w-24 mx-auto"></div>
                </div>
            </div>
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-6">
                    <div class="h-4 bg-gray-300 rounded w-36"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="space-y-2">
                                <div class="h-3.5 bg-gray-200 rounded w-20"></div>
                                <div class="h-10 bg-gray-50 rounded-xl"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

    @elseif ($type === 'details')
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gray-200"></div>
                    <div class="space-y-2">
                        <div class="h-5 bg-gray-300 rounded w-48"></div>
                        <div class="h-3 bg-gray-200 rounded w-32"></div>
                    </div>
                </div>
                <div class="h-9 bg-gray-100 rounded-lg w-28"></div>
            </div>

            <!-- Tab Headers -->
            <div class="border-b border-gray-200 flex gap-6">
                @for ($i = 0; $i < 5; $i++)
                    <div class="h-8 bg-gray-200 rounded w-16 pb-2"></div>
                @endfor
            </div>

            <!-- Tab Body -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
                        <div class="h-4 bg-gray-300 rounded w-36"></div>
                        <div class="h-3 bg-gray-200 rounded w-full"></div>
                        <div class="h-3 bg-gray-200 rounded w-11/12"></div>
                        <div class="h-3 bg-gray-200 rounded w-4/5"></div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm space-y-4">
                    <div class="h-4 bg-gray-300 rounded w-28"></div>
                    <div class="space-y-3">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="flex justify-between py-2 border-b border-gray-50 last:border-0">
                                <div class="h-3 bg-gray-200 rounded w-20"></div>
                                <div class="h-3 bg-gray-200 rounded w-16"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

    @else
        <!-- Generic Table Skeleton (default) -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="h-4 bg-gray-200 rounded w-32"></div>
                <div class="h-8 bg-gray-100 rounded-lg w-20"></div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-50 bg-[#FAFAF8]">
                            @for ($c = 0; $c < $cols; $c++)
                                <th class="p-4"><div class="h-3 bg-gray-300 rounded w-16"></div></th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @for ($r = 0; $r < $rows; $r++)
                            <tr class="border-b border-gray-50 last:border-0">
                                @for ($c = 0; $c < $cols; $c++)
                                    <td class="p-4">
                                        @if ($c === 0)
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded bg-gray-200"></div>
                                                <div class="h-3 bg-gray-300 rounded w-24"></div>
                                            </div>
                                        @else
                                            <div class="h-3 bg-gray-200 rounded w-16"></div>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
