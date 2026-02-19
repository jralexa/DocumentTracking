<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $documentCase->case_number }}</h2>
                <p class="text-sm text-gray-500">{{ $documentCase->title }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-700">
                    {{ str_replace('_', ' ', $documentCase->status) }}
                </span>

                @if (auth()->user()?->canManageDocuments())
                    @if ($documentCase->status === 'open')
                        <form method="POST" action="{{ route('cases.close', $documentCase) }}">
                            @csrf
                            <button
                                type="submit"
                                @disabled($caseMetrics['open_documents'] > 0)
                                class="inline-flex items-center rounded-md border border-rose-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent"
                                title="{{ $caseMetrics['open_documents'] > 0 ? 'All documents in the case must be finished before closing.' : 'Close this case' }}"
                            >
                                Close Case
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('cases.reopen', $documentCase) }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md border border-emerald-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-50"
                            >
                                Reopen Case
                            </button>
                        </form>
                    @endif
                @endif

                <a
                    href="{{ route('cases.index') }}"
                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                >
                    Back to Cases
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('case'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first('case') }}
                </div>
            @endif

            @include('documents.partials.monitor-tabs')

            @php
                $activeCaseTab = request()->query('case_tab', 'overview');
                if (! in_array($activeCaseTab, ['overview', 'documents', 'timeline'], true)) {
                    $activeCaseTab = 'overview';
                }
            @endphp

            <section x-data="{ activeCaseTab: @js($activeCaseTab) }" class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="activeCaseTab = 'overview'"
                            class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                            :class="activeCaseTab === 'overview' ? 'bg-slate-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            Overview
                        </button>
                        <button
                            type="button"
                            @click="activeCaseTab = 'documents'"
                            class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                            :class="activeCaseTab === 'documents' ? 'bg-slate-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            Documents
                        </button>
                        <button
                            type="button"
                            @click="activeCaseTab = 'timeline'"
                            class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                            :class="activeCaseTab === 'timeline' ? 'bg-slate-900 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            Timeline
                        </button>
                    </div>
                </div>

                <div x-show="activeCaseTab === 'overview'" x-cloak class="space-y-4">
                    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Documents</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $caseMetrics['total_documents'] }}</p>
                        </article>
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Open Documents</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $caseMetrics['open_documents'] }}</p>
                        </article>
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Overdue Documents</p>
                            <p class="mt-2 text-2xl font-semibold text-rose-700">{{ $caseMetrics['overdue_documents'] }}</p>
                        </article>
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Returnable Pending</p>
                            <p class="mt-2 text-2xl font-semibold text-amber-700">{{ $caseMetrics['returnable_pending'] }}</p>
                        </article>
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Returned</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ $caseMetrics['returned_documents'] }}</p>
                        </article>
                    </section>

                    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status Summary</h3>
                            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                                @forelse ($statusSummary as $status => $count)
                                    <li class="flex items-center justify-between">
                                        <span>{{ str_replace('_', ' ', ucfirst($status)) }}</span>
                                        <span class="font-semibold">{{ $count }}</span>
                                    </li>
                                @empty
                                    <li>No documents yet.</li>
                                @endforelse
                            </ul>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm md:col-span-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department Distribution</h3>
                            <ul class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 md:grid-cols-2">
                                @forelse ($departmentSummary as $departmentName => $count)
                                    <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2">
                                        <span>{{ $departmentName }}</span>
                                        <span class="font-semibold">{{ $count }}</span>
                                    </li>
                                @empty
                                    <li>No routing yet.</li>
                                @endforelse
                            </ul>
                        </article>
                    </section>
                </div>
                <section x-show="activeCaseTab === 'documents'" x-cloak class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Case Documents</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Current Holder</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Routing / Custody</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Relation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($documentCase->documents as $document)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $document->metadata['display_tracking'] ?? $document->tracking_number }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                            <p class="text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if ($document->returned_at)
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                                                    Returned
                                                </span>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ $document->returned_to ? 'to '.$document->returned_to : 'to owner' }}
                                                    - {{ $document->returned_at->format('M d, Y h:i A') }}
                                                </p>
                                            @else
                                                {{ $document->currentDepartment?->name ?? 'Unassigned' }}
                                                @if ($document->currentUser)
                                                    <span class="text-xs text-gray-500">({{ $document->currentUser->name }})</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <span>{{ str_replace('_', ' ', ucfirst($document->status->value)) }}</span>
                                            @if ($document->returned_at)
                                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                                    Returned document
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if ($document->latestTransfer?->forward_version_type)
                                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                                    Forwarded as {{ str_replace('_', ' ', $document->latestTransfer->forward_version_type->value) }}
                                                </p>
                                            @else
                                                <p class="text-xs text-gray-500">No forwarding metadata</p>
                                            @endif

                                            @if ($document->latestTransfer?->forward_version_type && $document->latestTransfer->forward_version_type->value !== 'original')
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Original kept at {{ $document->originalCurrentDepartment?->name ?? 'source department' }}
                                                    @if ($document->original_physical_location)
                                                        ({{ $document->original_physical_location }})
                                                    @endif
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @php
                                                $splitFromParent = $document->outgoingRelationships
                                                    ->first(fn ($rel) => $rel->relation_type->value === 'split_from');
                                                $isParentOfSplit = $document->incomingRelationships
                                                    ->contains(fn ($rel) => $rel->relation_type->value === 'split_from');
                                            @endphp
                                            @if ($splitFromParent)
                                                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                                    Child of {{ $splitFromParent->relatedDocument?->tracking_number }}
                                                </span>
                                            @elseif ($isParentOfSplit)
                                                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                                    Parent (Split Source)
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-500">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No documents linked to this case.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <section x-show="activeCaseTab === 'timeline'" x-cloak class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <h3 class="font-semibold text-gray-900">Case Timeline</h3>
                            <form method="GET" action="{{ route('cases.show', $documentCase) }}" class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-5">
                                <input type="hidden" name="case_tab" value="timeline">
                                <select
                                    name="event_type"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Events</option>
                                    @foreach ($timelineEventTypes as $eventType)
                                        <option value="{{ $eventType }}" @selected(($timelineFilters['event_type'] ?? null) === $eventType)>
                                            {{ str_replace('_', ' ', ucfirst($eventType)) }}
                                        </option>
                                    @endforeach
                                </select>

                                <input
                                    type="text"
                                    name="tracking_number"
                                    value="{{ $timelineFilters['tracking_number'] ?? '' }}"
                                    placeholder="Tracking Number"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />

                                <input
                                    type="date"
                                    name="from_date"
                                    value="{{ $timelineFilters['from_date'] ?? '' }}"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />

                                <input
                                    type="date"
                                    name="to_date"
                                    value="{{ $timelineFilters['to_date'] ?? '' }}"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />

                                <div class="flex items-center gap-2">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-700"
                                    >
                                        Apply
                                    </button>
                                    <a
                                        href="{{ route('cases.show', ['documentCase' => $documentCase, 'case_tab' => 'timeline']) }}"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">When</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Event</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Actor</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($timelineEvents as $timelineEvent)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $timelineEvent['occurred_at'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $timelineEvent['tracking_number'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $timelineEvent['event_type_label'] }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $timelineEvent['actor'] }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if ($timelineEvent['message'])
                                                {{ $timelineEvent['message'] }}
                                            @elseif ($timelineEvent['subject'])
                                                {{ $timelineEvent['subject'] }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No timeline events yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </div>
</x-app-layout>
