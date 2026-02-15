<x-guest-layout>
    <div class="space-y-5">
        <div class="text-center">
            <h1 class="text-xl font-semibold text-gray-900">Public Document Tracker</h1>
            <p class="mt-1 text-sm text-gray-500">Check current status using tracking number.</p>
        </div>

        <form method="GET" action="{{ route('documents.track.public') }}" class="space-y-3">
            <div>
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
            <x-primary-button class="w-full justify-center">
                {{ __('Track') }}
            </x-primary-button>
        </form>

        @if ($trackingNumber !== '' && $document === null)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                No document found for <span class="font-semibold">{{ $trackingNumber }}</span>.
            </div>
        @endif

        @if ($document)
            <section class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Document</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $document->metadata['display_tracking'] ?? $document->tracking_number }}</p>
                    <p class="text-sm text-gray-700">{{ $document->subject }}</p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Current Status</p>
                    <p class="text-sm text-gray-800">{{ str_replace('_', ' ', ucfirst($document->status->value)) }}</p>
                    <p class="text-xs text-gray-500">
                        Current office: {{ $document->currentDepartment?->name ?? 'Unassigned' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Routing History</p>
                    <ul class="mt-1 space-y-1 text-xs text-gray-700">
                        @forelse ($document->transfers->sortBy('id') as $transfer)
                            <li>
                                {{ $transfer->fromDepartment?->name ?? 'Initial Intake' }}
                                →
                                {{ $transfer->toDepartment?->name ?? '-' }}
                                ({{ str_replace('_', ' ', ucfirst($transfer->status->value)) }})
                            </li>
                        @empty
                            <li>No routing history yet.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        @endif
    </div>
</x-guest-layout>
