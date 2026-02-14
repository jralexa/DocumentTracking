<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <article class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                    <p class="text-xs uppercase text-gray-500">Active Alerts</p>
                    <p class="text-3xl font-semibold text-gray-900 mt-1">{{ $alertCounts['total_active'] }}</p>
                </article>
                <article class="bg-white border border-red-200 rounded-lg p-4 shadow-sm">
                    <p class="text-xs uppercase text-red-600">Overdue</p>
                    <p class="text-3xl font-semibold text-red-700 mt-1">{{ $alertCounts['overdue'] }}</p>
                </article>
                <article class="bg-white border border-amber-200 rounded-lg p-4 shadow-sm">
                    <p class="text-xs uppercase text-amber-700">Stalled</p>
                    <p class="text-3xl font-semibold text-amber-800 mt-1">{{ $alertCounts['stalled'] }}</p>
                </article>
            </section>

            <section class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-900">Recent Alerts</h3>
                    <p class="text-sm text-gray-500">Latest active alerts for your department.</p>
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
