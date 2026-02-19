<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Document Management: Edit') }}
            </h2>
            <a
                href="{{ route('documents.index') }}"
                class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
            >
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-5 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="POST" action="{{ route('documents.update', $document) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input-label for="subject" :value="__('Subject')" />
                            <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject', $document->subject)" required />
                            <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="reference_number" :value="__('Reference Number')" />
                            <x-text-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full" :value="old('reference_number', $document->reference_number)" />
                            <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="document_type" :value="__('Document Type')" />
                            <select id="document_type" name="document_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($documentTypes as $documentType)
                                    <option value="{{ $documentType }}" @selected(old('document_type', $document->document_type) === $documentType)>
                                        {{ str_replace('_', ' ', ucfirst($documentType)) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="owner_type" :value="__('Owner Type')" />
                            <select id="owner_type" name="owner_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($ownerTypes as $ownerType)
                                    <option value="{{ $ownerType }}" @selected(old('owner_type', $document->owner_type) === $ownerType)>
                                        {{ ucfirst($ownerType) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('owner_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="owner_name" :value="__('Owner Name')" />
                            <x-text-input id="owner_name" name="owner_name" type="text" class="mt-1 block w-full" :value="old('owner_name', $document->owner_name)" required />
                            <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="priority" :value="__('Priority')" />
                            <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', $document->priority) === $priority)>
                                        {{ ucfirst($priority) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="due_at" :value="__('Due Date')" />
                            <x-text-input id="due_at" name="due_at" type="date" class="mt-1 block w-full" :value="old('due_at', optional($document->due_at)->toDateString())" />
                            <x-input-error :messages="$errors->get('due_at')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2 rounded-md border border-gray-200 bg-gray-50 p-3" x-data="{ isReturnable: @js((bool) old('is_returnable', $document->is_returnable)) }">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    id="is_returnable"
                                    name="is_returnable"
                                    type="checkbox"
                                    value="1"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    x-model="isReturnable"
                                    @checked(old('is_returnable', $document->is_returnable))
                                >
                                Returnable Original
                            </label>

                            <div class="mt-3 max-w-sm" x-show="isReturnable" x-cloak>
                                <x-input-label for="return_deadline" :value="__('Return Deadline')" />
                                <x-text-input
                                    id="return_deadline"
                                    name="return_deadline"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :value="old('return_deadline', optional($document->return_deadline)->toDateString())"
                                />
                                <x-input-error :messages="$errors->get('return_deadline')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-3">
                        <a href="{{ route('documents.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </a>
                        <x-primary-button>
                            {{ __('Save Changes') }}
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
