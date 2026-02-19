<x-app-layout>
    @php
        $canUseAdvancedRouting = auth()->user()?->canManageDocuments() ?? false;
    @endphp
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Process Documents') }}
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
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
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
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Relation</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($onQueueDocuments as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->tracking_number }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ ucfirst($document->priority) }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->due_at?->format('M d, Y') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-left text-gray-700">
                                            @php
                                                $splitFromParent = $document->outgoingRelationships
                                                    ->first(fn ($relationship) => $relationship->relation_type->value === 'split_from');
                                                $isParentOfSplit = $document->incomingRelationships
                                                    ->contains(fn ($relationship) => $relationship->relation_type->value === 'split_from');
                                                $parentTracking = $splitFromParent?->relatedDocument?->metadata['display_tracking']
                                                    ?? $splitFromParent?->relatedDocument?->tracking_number;
                                                $isCaseLinked = ($document->documentCase?->documents_count ?? 0) > 1;
                                                $isCaseClosed = ($document->documentCase?->status ?? 'open') !== 'open';
                                                $canSplitDocument = ! $splitFromParent && ! $isCaseClosed;
                                            @endphp
                                            @if ($splitFromParent)
                                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">
                                                    Child
                                                </span>
                                                <p class="mt-1 text-xs text-gray-500">of {{ $parentTracking ?? '-' }}</p>
                                            @elseif ($isParentOfSplit)
                                                <span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-700">
                                                    Parent
                                                </span>
                                                <p class="mt-1 text-xs text-gray-500">Split Source</p>
                                            @elseif ($isCaseLinked)
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                                    Case-linked
                                                </span>
                                                <p class="mt-1 text-xs text-gray-500">{{ $document->documentCase?->case_number }}</p>
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div x-data="{ routeOpen: false, finishOpen: false }" class="relative inline-flex items-center gap-2">
                                                @if ($canSplitDocument)
                                                    <a
                                                        href="{{ route('documents.split.create', $document) }}"
                                                        class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                                                    >
                                                        Split
                                                    </a>
                                                @else
                                                    <span
                                                        class="inline-flex cursor-not-allowed items-center rounded-md border border-gray-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400"
                                                        title="{{ $isCaseClosed ? 'Case is closed. Reopen case before split.' : 'Child document cannot be split again.' }}"
                                                    >
                                                        Split
                                                    </span>
                                                @endif
                                                <button
                                                    type="button"
                                                    @click="routeOpen = !routeOpen; finishOpen = false"
                                                    :class="routeOpen ? 'border-slate-700 bg-slate-700 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                                    class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-semibold uppercase tracking-wide transition"
                                                >
                                                    Route
                                                </button>
                                                <template x-teleport="body">
                                                    <div
                                                        x-cloak
                                                        x-show="routeOpen"
                                                        x-transition.opacity
                                                        @keydown.escape.window="routeOpen = false"
                                                        class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/25 px-4 pb-6 pt-14"
                                                    >
                                                        <div
                                                            @click.outside="routeOpen = false"
                                                            class="w-full {{ $canUseAdvancedRouting ? 'max-w-lg' : 'max-w-md' }} overflow-hidden rounded-lg border border-gray-300 bg-white text-left text-gray-900 shadow-2xl"
                                                        >
                                                            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-100 px-3 py-2">
                                                                <div>
                                                                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500">
                                                                        Routing Context
                                                                    </p>
                                                                    <p class="line-clamp-1 rounded bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                                                        {{ $document->tracking_number }} - {{ $document->subject }}
                                                                    </p>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    @click="routeOpen = false"
                                                                    class="rounded border border-gray-300 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-200"
                                                                >
                                                                    Close
                                                                </button>
                                                            </div>
                                                            <form method="POST" action="{{ route('documents.forward', $document) }}" class="{{ $canUseAdvancedRouting ? 'max-h-[72vh] space-y-2 p-3' : 'max-h-[62vh] space-y-2 p-2.5' }} overflow-y-auto">
                                                                @csrf
                                                                <div>
                                                                    <x-input-label :value="__('Route To')" class="text-gray-700" />
                                                                    <select
                                                                        name="to_department_id"
                                                                        class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                                                    <x-input-label :value="__('Remarks (Optional)')" class="text-gray-700" />
                                                                    <textarea
                                                                        name="remarks"
                                                                        rows="2"
                                                                        class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                    ></textarea>
                                                                </div>
                                                                @if ($canUseAdvancedRouting)
                                                                    <details class="rounded-md border border-gray-300 bg-gray-50 p-2">
                                                                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-gray-700">
                                                                            Advanced Routing Options
                                                                        </summary>
                                                                        <div class="mt-2 space-y-2">
                                                                            <div>
                                                                                <x-input-label :value="__('Forward Version')" class="text-gray-700" />
                                                                                <select
                                                                                    name="forward_version_type"
                                                                                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                                >
                                                                                    <option value="original">Original</option>
                                                                                    <option value="certified_copy">Certified Copy</option>
                                                                                    <option value="photocopy">Photocopy</option>
                                                                                    <option value="scan">Digital Scan</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="rounded-md border border-gray-300 bg-white p-2">
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
                                                                                <x-input-label :value="__('Storage Location (if copy kept)')" class="text-gray-700" />
                                                                                <x-text-input
                                                                                    name="copy_storage_location"
                                                                                    type="text"
                                                                                    class="mt-1 block w-full border-gray-300 bg-white text-sm text-gray-900"
                                                                                    placeholder="e.g. Accounting Cabinet B-2"
                                                                                />
                                                                            </div>
                                                                            <div>
                                                                                <x-input-label :value="__('Copy Purpose (Optional)')" class="text-gray-700" />
                                                                                <textarea
                                                                                    name="copy_purpose"
                                                                                    rows="2"
                                                                                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                                    placeholder="e.g. For accounting audit trail"
                                                                                ></textarea>
                                                                            </div>
                                                                            <div>
                                                                                <x-input-label :value="__('Dispatch Method (Optional)')" class="text-gray-700" />
                                                                                <select
                                                                                    name="dispatch_method"
                                                                                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                                >
                                                                                    <option value="">Not specified</option>
                                                                                    <option value="walk_in">Walk-in</option>
                                                                                    <option value="courier">Courier</option>
                                                                                    <option value="email">Email</option>
                                                                                    <option value="system">System</option>
                                                                                </select>
                                                                            </div>
                                                                            <div>
                                                                                <x-input-label :value="__('Dispatch Reference (Optional)')" class="text-gray-700" />
                                                                                <x-text-input
                                                                                    name="dispatch_reference"
                                                                                    type="text"
                                                                                    class="mt-1 block w-full border-gray-300 bg-white text-sm text-gray-900"
                                                                                    placeholder="e.g. OR-2026-031 / Email Thread ID"
                                                                                />
                                                                            </div>
                                                                            <div>
                                                                                <x-input-label :value="__('Release Receipt Ref (Optional)')" class="text-gray-700" />
                                                                                <x-text-input
                                                                                    name="release_receipt_reference"
                                                                                    type="text"
                                                                                    class="mt-1 block w-full border-gray-300 bg-white text-sm text-gray-900"
                                                                                    placeholder="e.g. Signed receiving slip no."
                                                                                />
                                                                            </div>
                                                                        </div>
                                                                    </details>
                                                                @else
                                                                    <input type="hidden" name="forward_version_type" value="original">
                                                                    <p class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] text-gray-600">
                                                                        Basic mode: forwarding as original document.
                                                                    </p>
                                                                @endif
                                                                <div class="flex items-center gap-2 pt-1">
                                                                    <x-primary-button class="w-full justify-center text-xs">
                                                                        Route
                                                                    </x-primary-button>
                                                                    <button
                                                                        type="button"
                                                                        @click="routeOpen = false"
                                                                        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-100"
                                                                    >
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </template>
                                                <button
                                                    type="button"
                                                    @click="finishOpen = !finishOpen; routeOpen = false"
                                                    :class="finishOpen ? 'border-red-600 bg-red-600 text-white' : 'border-red-300 text-red-700 hover:bg-red-50'"
                                                    class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-semibold uppercase tracking-wide transition"
                                                >
                                                    Finish
                                                </button>
                                                <div
                                                    x-cloak
                                                    x-show="finishOpen"
                                                    class="absolute right-0 top-full z-40 mt-2 w-80 rounded-lg border border-red-300 bg-white p-3 text-left shadow-xl"
                                                >
                                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-red-700">
                                                        Finish
                                                    </p>
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
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No documents currently assigned for your action.</td>
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
                        <h3 class="font-semibold text-gray-900">Outgoing (Awaiting Acceptance)</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Destination</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Remarks</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($outgoingDocuments as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->tracking_number }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $document->latestTransfer?->toDepartment?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <p>{{ $document->latestTransfer?->remarks ?: '-' }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ $document->latestTransfer?->dispatch_method ?? '-' }}
                                                @if ($document->latestTransfer?->dispatch_reference)
                                                    - ref: {{ $document->latestTransfer?->dispatch_reference }}
                                                @endif
                                            </p>
                                        </td>
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

