<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Routing and Action Queues') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if ($errors->has('workflow'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first('workflow') }}
                </div>
            @endif

            <section class="space-y-5">
                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Incoming Documents</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">From</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Priority</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($incomingDocuments as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->tracking_number }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} · {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->latestTransfer?->fromDepartment?->name ?? 'Initial intake' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ ucfirst($document->priority) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('documents.accept', $document) }}">
                                                @csrf
                                                <x-primary-button class="text-xs">Accept</x-primary-button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No incoming documents for your department.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($incomingDocuments->hasPages())
                        <div class="border-t border-gray-200 px-4 py-3">
                            {{ $incomingDocuments->links() }}
                        </div>
                    @endif
                </article>

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">For Action</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Priority</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Due</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($onQueueDocuments as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->tracking_number }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} · {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ ucfirst($document->priority) }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->due_at?->format('M d, Y') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a
                                                    href="{{ route('documents.split.create', $document) }}"
                                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                                                >
                                                    Split
                                                </a>
                                                <details class="inline-block text-left">
                                                <summary class="cursor-pointer rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50">
                                                    Route
                                                </summary>
                                                <div class="mt-2 w-80 rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
                                                    <form method="POST" action="{{ route('documents.forward', $document) }}" class="space-y-2">
                                                        @csrf
                                                        <div>
                                                            <x-input-label :value="__('Route To')" />
                                                            <select
                                                                name="to_department_id"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                required
                                                            >
                                                                <option value="">Select Department</option>
                                                                @foreach ($activeDepartments as $department)
                                                                    @if ($department->id !== $document->current_department_id)
                                                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Forward Version')" />
                                                            <select
                                                                name="forward_version_type"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            >
                                                                <option value="original">Original</option>
                                                                <option value="certified_copy">Certified Copy</option>
                                                                <option value="photocopy">Photocopy</option>
                                                                <option value="scan">Digital Scan</option>
                                                            </select>
                                                        </div>
                                                        <div class="rounded-md border border-gray-200 bg-gray-50 p-2">
                                                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                                                <input
                                                                    type="checkbox"
                                                                    name="copy_kept"
                                                                    value="1"
                                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                >
                                                                Keep Photocopy in this office
                                                            </label>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Storage Location (if copy kept)')" />
                                                            <x-text-input
                                                                name="copy_storage_location"
                                                                type="text"
                                                                class="mt-1 block w-full text-sm"
                                                                placeholder="e.g. Accounting Cabinet B-2"
                                                            />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Copy Purpose (Optional)')" />
                                                            <textarea
                                                                name="copy_purpose"
                                                                rows="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                placeholder="e.g. For accounting audit trail"
                                                            ></textarea>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Action Taken / Remarks')" />
                                                            <textarea
                                                                name="remarks"
                                                                rows="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            ></textarea>
                                                        </div>
                                                        <x-primary-button class="w-full justify-center text-xs">
                                                            Route
                                                        </x-primary-button>
                                                    </form>
                                                </div>
                                                </details>
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer rounded-md border border-red-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700 hover:bg-red-50">
                                                        Finish
                                                    </summary>
                                                    <div class="mt-2 w-80 rounded-lg border border-red-200 bg-white p-3 shadow-lg">
                                                        <form method="POST" action="{{ route('documents.complete', $document) }}" class="space-y-2">
                                                            @csrf
                                                            <div>
                                                                <x-input-label :value="__('Completion Remarks (Optional)')" />
                                                                <textarea
                                                                    name="remarks"
                                                                    rows="3"
                                                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                    placeholder="e.g. Settled and approved, no further routing needed."
                                                                ></textarea>
                                                            </div>
                                                            <x-danger-button class="w-full justify-center text-xs">
                                                                Mark as Finished
                                                            </x-danger-button>
                                                        </form>
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No documents currently assigned for your action.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($onQueueDocuments->hasPages())
                        <div class="border-t border-gray-200 px-4 py-3">
                            {{ $onQueueDocuments->links() }}
                        </div>
                    @endif
                </article>

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Outgoing for Acceptance</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Destination</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Action Taken / Remarks</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($outgoingDocuments as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->tracking_number }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} · {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->latestTransfer?->toDepartment?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->latestTransfer?->remarks ?: '-' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            @if ($document->latestTransfer)
                                                <form method="POST" action="{{ route('documents.recall', $document->latestTransfer) }}">
                                                    @csrf
                                                    <x-secondary-button type="submit" class="text-xs">Recall Routing</x-secondary-button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No pending outgoing documents.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($outgoingDocuments->hasPages())
                        <div class="border-t border-gray-200 px-4 py-3">
                            {{ $outgoingDocuments->links() }}
                        </div>
                    @endif
                </article>
            </section>
        </div>
    </div>
</x-app-layout>
