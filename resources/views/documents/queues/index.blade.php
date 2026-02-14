<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Document Queues') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('workflow'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    {{ $errors->first('workflow') }}
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <section class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">Incoming</h3>
                        <p class="text-sm text-gray-500">Pending documents for your department.</p>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse ($incomingDocuments as $document)
                            <article class="border border-gray-200 rounded-md p-3 space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500">{{ $document->tracking_number }}</p>
                                    <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                    <p class="text-sm text-gray-600">{{ $document->document_type }} · {{ $document->owner_name }}</p>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <p>From: {{ $document->latestTransfer?->fromDepartment?->name ?? 'Initial intake' }}</p>
                                    <p>Forwarded by: {{ $document->latestTransfer?->forwardedBy?->name ?? 'Unknown' }}</p>
                                </div>
                                <form method="POST" action="{{ route('documents.accept', $document) }}">
                                    @csrf
                                    <x-primary-button class="w-full justify-center">
                                        {{ __('Accept') }}
                                    </x-primary-button>
                                </form>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">No incoming documents.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">On Queue</h3>
                        <p class="text-sm text-gray-500">Documents currently assigned to you.</p>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse ($onQueueDocuments as $document)
                            <article class="border border-gray-200 rounded-md p-3 space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500">{{ $document->tracking_number }}</p>
                                    <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                    <p class="text-sm text-gray-600">{{ $document->document_type }} · {{ $document->owner_name }}</p>
                                </div>

                                <form method="POST" action="{{ route('documents.forward', $document) }}" class="space-y-2">
                                    @csrf
                                    <div>
                                        <x-input-label :value="__('Forward To')" />
                                        <select
                                            name="to_department_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                            <option value="">{{ __('Select Department') }}</option>
                                            @foreach ($activeDepartments as $department)
                                                @if ($department->id !== $document->current_department_id)
                                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Remarks')" />
                                        <textarea
                                            name="remarks"
                                            rows="2"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        ></textarea>
                                    </div>
                                    <x-primary-button class="w-full justify-center">
                                        {{ __('Forward') }}
                                    </x-primary-button>
                                </form>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">No documents in your personal queue.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">Outgoing</h3>
                        <p class="text-sm text-gray-500">Forwarded by you and waiting for acceptance.</p>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse ($outgoingDocuments as $document)
                            <article class="border border-gray-200 rounded-md p-3 space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500">{{ $document->tracking_number }}</p>
                                    <p class="font-medium text-gray-900">{{ $document->subject }}</p>
                                    <p class="text-sm text-gray-600">
                                        To: {{ $document->latestTransfer?->toDepartment?->name ?? 'Unknown Department' }}
                                    </p>
                                </div>
                                @if ($document->latestTransfer?->remarks)
                                    <p class="text-xs text-gray-600">Remarks: {{ $document->latestTransfer->remarks }}</p>
                                @endif
                                <form method="POST" action="{{ route('documents.recall', $document->latestTransfer) }}">
                                    @csrf
                                    <x-secondary-button class="w-full justify-center">
                                        {{ __('Recall') }}
                                    </x-secondary-button>
                                </form>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">No pending outgoing documents.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

