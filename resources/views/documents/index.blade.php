<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Document List') }}</h2>
            @if (auth()->user()?->canManageDocuments())
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Manage Access</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">View Only</span>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @include('documents.partials.monitor-tabs')

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('documents.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <x-input-label for="q" :value="__('Search')" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filters['q']" placeholder="Tracking, subject, owner, case..." />
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="document_type" :value="__('Type')" />
                        <select id="document_type" name="document_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            @foreach ($documentTypes as $documentType)
                                <option value="{{ $documentType }}" @selected($filters['document_type'] === $documentType)>{{ str_replace('_', ' ', ucfirst($documentType)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="owner_type" :value="__('Owner Type')" />
                        <select id="owner_type" name="owner_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            @foreach ($ownerTypes as $ownerType)
                                <option value="{{ $ownerType }}" @selected($filters['owner_type'] === $ownerType)>{{ ucfirst($ownerType) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="department_id" :value="__('Current Dept')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            @foreach ($activeDepartments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-6 flex items-center justify-end gap-2">
                        <a href="{{ route('documents.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50">
                            Reset
                        </a>
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
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Case</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Current Office</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Updated</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($documents as $document)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                        <p class="text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        @if ($document->documentCase)
                                            <p class="font-medium">{{ $document->documentCase->case_number }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->documentCase->title }}</p>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ str_replace('_', ' ', ucfirst($document->status->value)) }}</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $document->currentDepartment?->name ?? 'Unassigned' }}
                                        @if ($document->currentUser)
                                            <span class="text-xs text-gray-500">({{ $document->currentUser->name }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $document->updated_at?->format('M d, Y h:i A') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('documents.track', ['tracking_number' => $document->metadata['display_tracking'] ?? $document->tracking_number]) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50">
                                                Track
                                            </a>
                                            @if (auth()->user()?->canManageDocuments())
                                                <a href="{{ route('documents.edit', $document) }}" class="inline-flex items-center rounded-md border border-indigo-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 transition hover:bg-indigo-50">
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and related records? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-md border border-rose-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-700 transition hover:bg-rose-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No matching documents found.</td>
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
