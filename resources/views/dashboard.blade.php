<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($canProcessDepartmentQueues)
                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <article class="bg-white border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-slate-500">Received for Action</p>
                        <p class="text-3xl font-semibold text-slate-900 mt-1">{{ $queueCounts['incoming'] }}</p>
                    </article>
                    <article class="bg-white border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-slate-500">In Process (My Queue)</p>
                        <p class="text-3xl font-semibold text-slate-900 mt-1">{{ $queueCounts['on_queue'] }}</p>
                    </article>
                    <article class="bg-white border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-slate-500">Forwarded for Acceptance</p>
                        <p class="text-3xl font-semibold text-slate-900 mt-1">{{ $queueCounts['outgoing'] }}</p>
                    </article>
                    <article class="bg-white border border-red-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-red-600">Past Due Workload</p>
                        <p class="text-3xl font-semibold text-red-700 mt-1">{{ $workflowHighlights['overdue'] }}</p>
                    </article>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <article class="bg-white border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-slate-500">Due for Action Today</p>
                        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $workflowHighlights['due_today'] }}</p>
                    </article>
                    <article class="bg-white border border-amber-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-amber-700">Overdue Returnable Originals</p>
                        <p class="text-2xl font-semibold text-amber-800 mt-1">{{ $workflowHighlights['returnable_overdue'] }}</p>
                    </article>
                    <article class="bg-white border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase text-slate-500">Original Custody / Active Copies</p>
                        <p class="text-2xl font-semibold text-slate-900 mt-1">
                            {{ $custodyHighlights['originals_in_custody'] }}
                            <span class="text-slate-400 font-medium">/</span>
                            {{ $custodyHighlights['active_copies'] }}
                        </p>
                    </article>
                </section>
            @endif

            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Active Office Alerts</p>
                    <p class="text-3xl font-semibold text-gray-900 mt-1">{{ $alertCounts['total_active'] }}</p>
                </article>
                <article class="bg-white border border-red-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-red-600">Alert: Past Due</p>
                    <p class="text-3xl font-semibold text-red-700 mt-1">{{ $alertCounts['overdue'] }}</p>
                </article>
                <article class="bg-white border border-amber-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-amber-700">Alert: Stalled in Queue</p>
                    <p class="text-3xl font-semibold text-amber-800 mt-1">{{ $alertCounts['stalled'] }}</p>
                </article>
            </section>

            @if ($canProcessDepartmentQueues)
                <section class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">Recent Document Routing</h3>
                        <p class="text-sm text-gray-500">Latest endorsements and forwards involving your office.</p>
                    </div>
                    <div class="p-4">
                        @if ($recentTransfers->isEmpty())
                            <p class="text-sm text-gray-500">No recent transfer activity yet.</p>
                        @else
                            <div class="overflow-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-gray-500">
                                            <th class="py-2 pr-4">Tracking</th>
                                            <th class="py-2 pr-4">Subject</th>
                                            <th class="py-2 pr-4">Route Path</th>
                                            <th class="py-2 pr-4">Forwarded By</th>
                                            <th class="py-2">Date Forwarded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentTransfers as $transfer)
                                            <tr class="border-t border-gray-100">
                                                <td class="py-2 pr-4">{{ $transfer->document?->tracking_number ?? '-' }}</td>
                                                <td class="py-2 pr-4">{{ $transfer->document?->subject ?? '-' }}</td>
                                                <td class="py-2 pr-4">
                                                    {{ $transfer->fromDepartment?->name ?? 'Intake' }}
                                                    <span class="text-gray-400">→</span>
                                                    {{ $transfer->toDepartment?->name ?? '-' }}
                                                </td>
                                                <td class="py-2 pr-4">{{ $transfer->forwardedBy?->name ?? '-' }}</td>
                                                <td class="py-2">{{ $transfer->forwarded_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section class="bg-white border border-gray-200 rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-900">Recent Alert Notices</h3>
                    <p class="text-sm text-gray-500">Latest active notices for your office.</p>
                </div>
                <div class="p-4">
                    @if ($recentAlerts->isEmpty())
                        <p class="text-sm text-gray-500">No active alerts for your current view.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($recentAlerts as $alert)
                                <article class="border border-gray-200 rounded-md p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $alert->document?->tracking_number ?? 'No Tracking Number' }}
                                                -
                                                {{ $alert->document?->subject ?? 'Unknown Document' }}
                                            </p>
                                            <p class="text-sm text-gray-600 mt-1">{{ $alert->message }}</p>
                                        </div>
                                        <span class="text-xs uppercase tracking-wide px-2 py-1 rounded-full {{ $alert->alert_type->value === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $alert->alert_type->value }}
                                        </span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
