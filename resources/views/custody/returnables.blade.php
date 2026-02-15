<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Returnable Documents') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if ($errors->has('returnable'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first('returnable') }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('custody.returnables.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <x-input-label for="q" :value="__('Search')" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filters['q']" placeholder="Tracking, subject, owner..." />
                    </div>
                    <div>
                        <x-input-label for="state" :value="__('State')" />
                        <select id="state" name="state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="pending" @selected($filters['state'] === 'pending')>Pending Return</option>
                            <option value="overdue" @selected($filters['state'] === 'overdue')>Overdue</option>
                            <option value="returned" @selected($filters['state'] === 'returned')>Returned</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <a href="{{ route('custody.returnables.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50">Reset</a>
                        <x-primary-button>{{ __('Search') }}</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Current Original Holder</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Return Deadline</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Return Status</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($documents as $document)
                                @php
                                    $isOverdue = $document->returned_at === null && $document->return_deadline !== null && $document->return_deadline->isPast();
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                        <p class="text-xs text-gray-500">{{ $document->owner_name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $document->originalCurrentDepartment?->name ?? 'Unassigned' }}
                                        @if ($document->originalCustodian)
                                            <span class="text-xs text-gray-500">({{ $document->originalCustodian->name }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $document->return_deadline?->format('M d, Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        @if ($document->returned_at)
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">
                                                Returned
                                            </span>
                                        @elseif ($isOverdue)
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">
                                                Overdue
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($document->returned_at === null)
                                            <details class="inline-block text-left">
                                                <summary class="cursor-pointer rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50">
                                                    Mark Returned
                                                </summary>
                                                <div class="mt-2 w-80 rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
                                                    <form method="POST" action="{{ route('custody.returnables.returned', $document) }}" class="space-y-2">
                                                        @csrf
                                                        <div>
                                                            <x-input-label :value="__('Returned To')" />
                                                            <x-text-input name="returned_to" type="text" class="mt-1 block w-full text-sm" required />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Returned At (Optional)')" />
                                                            <x-text-input name="returned_at" type="datetime-local" class="mt-1 block w-full text-sm" />
                                                        </div>
                                                        <x-primary-button class="w-full justify-center text-xs">Submit</x-primary-button>
                                                    </form>
                                                </div>
                                            </details>
                                        @else
                                            <span class="text-xs text-gray-500">{{ $document->returned_at?->format('M d, Y h:i A') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No returnable documents found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($documents->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3">
                        {{ $documents->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
