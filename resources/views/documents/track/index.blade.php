<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Track Document') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('documents.track') }}" class="grid grid-cols-1 items-end gap-3 md:grid-cols-4">
                    <div class="md:col-span-3">
                        <x-input-label for="tracking_number" :value="__('Tracking Number')" />
                        <x-text-input
                            id="tracking_number"
                            name="tracking_number"
                            type="text"
                            class="mt-1 block w-full"
                            :value="$trackingNumber"
                            placeholder="e.g. 260214033 or 260214033-A"
                            required
                        />
                    </div>
                    <x-primary-button class="justify-center">
                        {{ __('Track') }}
                    </x-primary-button>
                </form>
            </section>

            @if ($trackingNumber !== '' && $document === null)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    No document found for tracking number <span class="font-semibold">{{ $trackingNumber }}</span>.
                </div>
            @endif

            @if ($document)
                <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Document</h3>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</p>
                        <p class="text-sm text-gray-700">{{ $document->subject }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $document->document_type }} - {{ $document->owner_name }}</p>
                    </article>

                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Status</h3>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ str_replace('_', ' ', ucfirst($document->status->value)) }}</p>
                        <p class="text-xs text-gray-500">
                            Holder: {{ $document->currentDepartment?->name ?? 'Unassigned' }}
                            @if ($document->currentUser)
                                ({{ $document->currentUser->name }})
                            @endif
                        </p>
                    </article>

                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Original Custody</h3>
                        <p class="mt-2 text-sm text-gray-800">
                            {{ $document->originalCurrentDepartment?->name ?? 'No current original custody record' }}
                        </p>
                        @if ($document->original_physical_location)
                            <p class="text-xs text-gray-500">{{ $document->original_physical_location }}</p>
                        @endif
                    </article>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Routing Timeline</h3>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Forwarded At</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">From</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">To</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Version</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($document->transfers->sortBy('id') as $transfer)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $transfer->forwarded_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $transfer->fromDepartment?->name ?? 'Initial Intake' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $transfer->toDepartment?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $transfer->forward_version_type?->value ?? 'original' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ str_replace('_', ' ', ucfirst($transfer->status->value)) }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $transfer->remarks ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No transfer history available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <header class="border-b border-gray-200 px-4 py-3">
                            <h3 class="font-semibold text-gray-900">Attachments</h3>
                        </header>
                        <ul class="divide-y divide-gray-100">
                            @forelse ($document->attachments as $attachment)
                                <li class="px-4 py-3 text-sm text-gray-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $attachment->original_name }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ number_format(($attachment->size_bytes ?? 0) / 1024, 1) }} KB
                                                @if ($attachment->uploadedBy)
                                                    - uploaded by {{ $attachment->uploadedBy->name }}
                                                @endif
                                            </p>
                                        </div>
                                        <a
                                            href="{{ route('documents.attachments.download', [$document, $attachment]) }}"
                                            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                                        >
                                            Download
                                        </a>
                                    </div>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-sm text-gray-500">No attachments uploaded.</li>
                            @endforelse
                        </ul>
                    </article>

                    <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <header class="border-b border-gray-200 px-4 py-3">
                            <h3 class="font-semibold text-gray-900">Active Copy Inventory</h3>
                        </header>
                        <ul class="divide-y divide-gray-100">
                            @forelse ($document->copies as $copy)
                                <li class="px-4 py-3 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">{{ $copy->copy_type->value }}</p>
                                    <p>{{ $copy->department?->name ?? 'Unknown Department' }}</p>
                                    <p class="text-xs text-gray-500">{{ $copy->storage_location ?? 'No storage location' }}</p>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-sm text-gray-500">No copy records.</li>
                            @endforelse
                        </ul>
                    </article>

                    <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <header class="border-b border-gray-200 px-4 py-3">
                            <h3 class="font-semibold text-gray-900">Current Custody Records</h3>
                        </header>
                        <ul class="divide-y divide-gray-100">
                            @forelse ($document->custodies->sortByDesc('id') as $custody)
                                <li class="px-4 py-3 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">{{ $custody->version_type->value }} - {{ $custody->status }}</p>
                                    <p>{{ $custody->department?->name ?? 'Unknown Department' }}</p>
                                    <p class="text-xs text-gray-500">{{ $custody->physical_location ?? 'No physical location' }}</p>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-sm text-gray-500">No custody records.</li>
                            @endforelse
                        </ul>
                    </article>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
