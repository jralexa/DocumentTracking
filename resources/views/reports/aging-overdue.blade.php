<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Aging / Overdue Report') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
                <form method="GET" action="{{ route('reports.aging-overdue') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <x-input-label for="department_id" :value="__('Department')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Departments</option>
                            @foreach ($activeDepartments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="overdue_days" :value="__('Aging Threshold (Days)')" />
                        <x-text-input id="overdue_days" type="number" min="1" max="120" name="overdue_days" class="mt-1 block w-full" :value="$filters['overdue_days']" />
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Generate') }}</x-primary-button>
                    </div>
                    <div class="md:text-right">
                        <a href="{{ route('reports.aging-overdue') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 transition">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Open Documents</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['open_total'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">With Due Date</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['with_due_date'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Overdue</p>
                    <p class="text-2xl font-semibold text-red-700">{{ $metrics['overdue_count'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Aging (&gt;={{ $filters['overdue_days'] }} days idle)</p>
                    <p class="text-2xl font-semibold text-amber-700">{{ $metrics['aging_count'] }}</p>
                </div>
            </section>

            <section class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-3">Overdue Documents</h3>
                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Tracking</th>
                                <th class="py-2 pr-4">Subject</th>
                                <th class="py-2 pr-4">Department</th>
                                <th class="py-2 pr-4">Due Date</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overdueDocuments as $document)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-4">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</td>
                                    <td class="py-2 pr-4">{{ $document->subject }}</td>
                                    <td class="py-2 pr-4">{{ $document->currentDepartment?->name ?? 'Unassigned' }}</td>
                                    <td class="py-2 pr-4">{{ $document->due_at?->format('M d, Y') ?? '-' }}</td>
                                    <td class="py-2">{{ str_replace('_', ' ', ucfirst($document->status->value)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-2 text-gray-500" colspan="5">No overdue documents for selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($overdueDocuments->hasPages())
                    <div class="mt-4">
                        {{ $overdueDocuments->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
