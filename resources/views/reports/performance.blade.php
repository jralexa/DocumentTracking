<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Performance Report') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
                <form method="GET" action="{{ route('reports.performance') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
                        <x-input-label for="month" :value="__('Month')" />
                        <x-text-input id="month" type="month" name="month" class="mt-1 block w-full" :value="$selectedMonth" />
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Generate') }}</x-primary-button>
                    </div>
                    <div class="md:text-right">
                        <a href="{{ route('reports.performance') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 transition">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Forwarded</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['forwarded_count'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Received</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['received_count'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Accepted</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['accepted_count'] }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Avg Acceptance Hours</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $metrics['average_acceptance_hours'] }}</p>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Department Throughput</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Department</th>
                                    <th class="py-2">Processed Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departmentPerformance as $row)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $row->department_name }}</td>
                                        <td class="py-2">{{ $row->processed_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="2">No performance data for selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Top Forwarding Users</h3>
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">User</th>
                                    <th class="py-2">Forwarded Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($userPerformance as $row)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-4">{{ $row->user_name }}</td>
                                        <td class="py-2">{{ $row->forwarded_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 text-gray-500" colspan="2">No user productivity data for selected filters.</td>
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
