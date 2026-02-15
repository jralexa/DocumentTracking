<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('SLA Compliance Report') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('reports.partials.tabs')

            <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
                <form method="GET" action="{{ route('reports.sla-compliance') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <x-input-label for="department_id" :value="__('Department')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if ($canViewAllDepartments)
                                <option value="">All Departments</option>
                            @endif
                            @foreach ($activeDepartments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="month" :value="__('Month')" />
                        <x-text-input id="month" type="month" name="month" class="mt-1 block w-full" :value="$selectedMonth" />
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Generate') }}</x-primary-button>
                    </div>
                    <div class="md:text-right">
                        <a href="{{ route('reports.sla-compliance') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 transition">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Closed Total</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['closed_total'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Measured Closed</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['measured_closed_total'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Within SLA</p>
                    <p class="text-2xl font-semibold text-green-700">{{ $metrics['completed_within_sla'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Breached</p>
                    <p class="text-2xl font-semibold text-red-700">{{ $metrics['completed_breached'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">No Due Date</p>
                    <p class="text-2xl font-semibold text-amber-700">{{ $metrics['completed_without_due_date'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Compliance Rate</p>
                    <p class="text-2xl font-semibold text-indigo-700">{{ $metrics['compliance_rate'] === null ? 'N/A' : number_format($metrics['compliance_rate'], 2).'%' }}</p>
                    <p class="mt-1 text-xs text-gray-500">Open past due: {{ $metrics['open_past_due'] }}</p>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Compliance by Document Type</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Document Type</th>
                                    <th class="py-2 pr-4">Measured</th>
                                    <th class="py-2 pr-4">Within SLA</th>
                                    <th class="py-2 pr-4">Breached</th>
                                    <th class="py-2">Compliance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($byDocumentType as $row)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $row->group_value !== null && $row->group_value !== '' ? $row->group_value : 'Unspecified' }}</td>
                                        <td class="py-2 pr-4">{{ (int) $row->measured_closed_total }}</td>
                                        <td class="py-2 pr-4 text-green-700">{{ (int) $row->completed_within_sla }}</td>
                                        <td class="py-2 pr-4 text-red-700">{{ (int) $row->completed_breached }}</td>
                                        <td class="py-2">{{ $row->compliance_rate === null ? 'N/A' : number_format((float) $row->compliance_rate, 2).'%' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="5">No completed documents for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Compliance by Priority</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Priority</th>
                                    <th class="py-2 pr-4">Measured</th>
                                    <th class="py-2 pr-4">Within SLA</th>
                                    <th class="py-2 pr-4">Breached</th>
                                    <th class="py-2">Compliance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($byPriority as $row)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $row->group_value !== null && $row->group_value !== '' ? ucfirst($row->group_value) : 'Unspecified' }}</td>
                                        <td class="py-2 pr-4">{{ (int) $row->measured_closed_total }}</td>
                                        <td class="py-2 pr-4 text-green-700">{{ (int) $row->completed_within_sla }}</td>
                                        <td class="py-2 pr-4 text-red-700">{{ (int) $row->completed_breached }}</td>
                                        <td class="py-2">{{ $row->compliance_rate === null ? 'N/A' : number_format((float) $row->compliance_rate, 2).'%' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="5">No completed documents for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Breached Closed Documents</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Tracking</th>
                                    <th class="py-2 pr-4">Subject</th>
                                    <th class="py-2 pr-4">Department</th>
                                    <th class="py-2">Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($breachedCompletedDocuments as $document)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</td>
                                        <td class="py-2 pr-4">{{ $document->subject }}</td>
                                        <td class="py-2 pr-4">{{ $document->currentDepartment?->name ?? 'Unassigned' }}</td>
                                        <td class="py-2">{{ $document->completed_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="4">No breached closed documents for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Open Past Due Documents</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Tracking</th>
                                    <th class="py-2 pr-4">Subject</th>
                                    <th class="py-2 pr-4">Department</th>
                                    <th class="py-2">Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openPastDueDocuments as $document)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</td>
                                        <td class="py-2 pr-4">{{ $document->subject }}</td>
                                        <td class="py-2 pr-4">{{ $document->currentDepartment?->name ?? 'Unassigned' }}</td>
                                        <td class="py-2">{{ $document->due_at?->format('M d, Y') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="4">No open past due documents for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </div>
</x-app-layout>
