<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Split Document for Multi-Department Routing') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Please review the split entries and fix validation errors.
                </div>
            @endif

            @php
                $defaultMode = old('children.0.routing_mode', 'branch');
                $oldDestinationIds = collect(old('children.0.to_department_ids', []))
                    ->map(static fn ($id): string => (string) $id)
                    ->values()
                    ->all();
                $oldPrimaryDestinationId = (string) (old('children.0.to_department_ids.0') ?? ($oldDestinationIds[0] ?? ''));
                $canUseAdvancedRouting = auth()->user()?->canManageDocuments() ?? false;
            @endphp

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent Document</h3>
                <div class="mt-2 grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-3">
                    <p><span class="font-semibold text-gray-900">Tracking:</span> {{ $parentDocument->tracking_number }}</p>
                    <p><span class="font-semibold text-gray-900">Subject:</span> {{ $parentDocument->subject }}</p>
                    <p><span class="font-semibold text-gray-900">Current Department:</span> {{ $parentDocument->currentDepartment?->name ?? '-' }}</p>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Auto suffix starts from: {{ $nextSuffix }}.
                </p>
            </section>

            <section
                x-data="{ mode: @js($defaultMode === 'child' ? 'child' : 'branch') }"
                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
            >
                <header class="border-b border-gray-200 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="mode = 'branch'"
                            class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                            :class="mode === 'branch' ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            Route Existing
                        </button>
                        <button
                            type="button"
                            @click="mode = 'child'"
                            class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                            :class="mode === 'child' ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        >
                            Create Child
                        </button>
                    </div>
                    @if ($canUseAdvancedRouting)
                        <p class="mt-2 text-xs text-gray-500">
                            Use <span class="font-semibold text-gray-700">Route Existing</span> for the same document sent to another office.
                            Use <span class="font-semibold text-gray-700">Create Child</span> for a separate related document record.
                        </p>
                    @endif
                </header>

                <div x-show="mode === 'branch'" x-cloak class="p-4">
                    <form
                        method="POST"
                        action="{{ route('documents.split.store', $parentDocument) }}"
                        x-data="{
                            forwardVersion: @js($canUseAdvancedRouting ? old('children.0.forward_version_type', 'original') : 'original'),
                            destinationIds: @js($oldDestinationIds),
                            copyKept: @js($canUseAdvancedRouting && (bool) old('children.0.copy_kept')),
                            returnable: @js((bool) old('children.0.is_returnable')),
                        }"
                        class="space-y-4"
                    >
                        @csrf

                        <input type="hidden" name="children[0][routing_mode]" value="branch">
                        <input type="hidden" name="children[0][subject]" value="{{ $parentDocument->subject }}">
                        <input type="hidden" name="children[0][document_type]" value="{{ $parentDocument->document_type }}">
                        <input type="hidden" name="children[0][same_owner_as_parent]" value="1">
                        <input type="hidden" name="children[0][owner_type]" value="{{ $parentDocument->owner_type }}">
                        <input type="hidden" name="children[0][owner_name]" value="{{ $parentDocument->owner_name }}">
                        @if (! $canUseAdvancedRouting)
                            <input type="hidden" name="children[0][forward_version_type]" value="original">
                        @endif

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @if ($canUseAdvancedRouting)
                                <div>
                                    <x-input-label for="branch_forward_version_type" :value="__('Forward Version')" />
                                    <select
                                        id="branch_forward_version_type"
                                        name="children[0][forward_version_type]"
                                        x-model="forwardVersion"
                                        @change="if (forwardVersion === 'original' && destinationIds.length > 1) { destinationIds = [destinationIds[0]]; } if (forwardVersion !== 'original') { copyKept = false; }"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="original">Original</option>
                                        <option value="certified_copy">Certified Copy</option>
                                        <option value="photocopy">Photocopy</option>
                                        <option value="scan">Digital Scan</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('children.0.forward_version_type')" class="mt-2" />
                                </div>
                            @else
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    Basic mode: routing uses <span class="font-semibold text-gray-800">Original</span> document version.
                                </div>
                            @endif

                            <div>
                                <x-input-label for="branch_remarks" :value="__('Remarks (Optional)')" />
                                <textarea
                                    id="branch_remarks"
                                    name="children[0][remarks]"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >{{ old('children.0.remarks') }}</textarea>
                                <x-input-error :messages="$errors->get('children.0.remarks')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label :value="$canUseAdvancedRouting ? __('Route To Departments') : __('Route To')" />
                            @if ($canUseAdvancedRouting)
                                <p class="mt-1 text-xs text-gray-500">
                                    Original version can route to one department only. Use copy versions for multi-department routing.
                                </p>
                                <div class="mt-2 grid grid-cols-1 gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 md:grid-cols-2">
                                    @foreach ($activeDepartments as $department)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                name="children[0][to_department_ids][]"
                                                value="{{ $department->id }}"
                                                x-model="destinationIds"
                                                :disabled="forwardVersion === 'original' && !destinationIds.includes('{{ (string) $department->id }}') && destinationIds.length >= 1"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            >
                                            {{ $department->name }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <select
                                    name="children[0][to_department_ids][]"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Select Department</option>
                                    @foreach ($activeDepartments as $department)
                                        <option value="{{ $department->id }}" @selected($oldPrimaryDestinationId === (string) $department->id)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <x-input-error :messages="$errors->get('children.0.to_department_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('children.0.to_department_ids.*')" class="mt-2" />
                        </div>
                        @if ($canUseAdvancedRouting)
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-3" x-show="forwardVersion === 'original'" x-cloak>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                    <input
                                        type="checkbox"
                                        name="children[0][copy_kept]"
                                        value="1"
                                        x-model="copyKept"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Keep a photocopy in source department
                                </label>

                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2" x-show="copyKept" x-cloak>
                                    <div>
                                        <x-input-label for="branch_copy_storage_location" :value="__('Copy Storage Location')" />
                                        <x-text-input
                                            id="branch_copy_storage_location"
                                            name="children[0][copy_storage_location]"
                                            type="text"
                                            class="mt-1 block w-full"
                                            :value="old('children.0.copy_storage_location')"
                                            placeholder="e.g. Cabinet B-2"
                                        />
                                        <x-input-error :messages="$errors->get('children.0.copy_storage_location')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="branch_copy_purpose" :value="__('Copy Purpose (Optional)')" />
                                        <x-text-input
                                            id="branch_copy_purpose"
                                            name="children[0][copy_purpose]"
                                            type="text"
                                            class="mt-1 block w-full"
                                            :value="old('children.0.copy_purpose')"
                                            placeholder="e.g. Audit reference"
                                        />
                                        <x-input-error :messages="$errors->get('children.0.copy_purpose')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-md border border-indigo-200 bg-indigo-50 p-3" x-show="forwardVersion !== 'original'" x-cloak>
                                <x-input-label for="branch_original_storage_location" :value="__('Original Storage Location')" class="text-indigo-900" />
                                <x-text-input
                                    id="branch_original_storage_location"
                                    name="children[0][original_storage_location]"
                                    type="text"
                                    class="mt-1 block w-full md:w-2/3"
                                    :value="old('children.0.original_storage_location')"
                                    placeholder="e.g. Records Vault Shelf A"
                                />
                                <p class="mt-1 text-xs text-indigo-700">Required when forwarding copy versions.</p>
                                <x-input-error :messages="$errors->get('children.0.original_storage_location')" class="mt-2" />
                            </div>
                        @endif

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                <input
                                    type="checkbox"
                                    name="children[0][is_returnable]"
                                    value="1"
                                    x-model="returnable"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                Returnable original document
                            </label>

                            <div class="mt-3" x-show="returnable" x-cloak>
                                <x-input-label for="branch_return_deadline" :value="__('Return Deadline')" />
                                <x-text-input
                                    id="branch_return_deadline"
                                    name="children[0][return_deadline]"
                                    type="date"
                                    class="mt-1 block w-full md:w-64"
                                    :value="old('children.0.return_deadline')"
                                />
                                <x-input-error :messages="$errors->get('children.0.return_deadline')" class="mt-2" />
                            </div>
                        </div>

                        @if ($canUseAdvancedRouting)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <label class="inline-flex items-start gap-2 text-sm text-amber-900">
                                    <input
                                        type="checkbox"
                                        name="confirm_routing_reviewed"
                                        value="1"
                                        class="mt-0.5 rounded border-amber-300 text-indigo-600 focus:ring-indigo-500"
                                        @checked(old('confirm_routing_reviewed'))
                                        required
                                    >
                                    I reviewed the routing details before submitting.
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="confirm_routing_reviewed" value="1">
                        @endif

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                            <a
                                href="{{ route('documents.queues.index') }}"
                                class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                            >
                                Cancel
                            </a>
                            <x-primary-button>{{ __('Route') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                <div x-show="mode === 'child'" x-cloak class="p-4">
                    <form
                        method="POST"
                        action="{{ route('documents.split.store', $parentDocument) }}"
                        x-data="{
                            sameOwner: @js(old('children.0.same_owner_as_parent', '1') !== '0'),
                            forwardVersion: @js($canUseAdvancedRouting ? old('children.0.forward_version_type', 'original') : 'original'),
                            destinationIds: @js($oldDestinationIds),
                            copyKept: @js($canUseAdvancedRouting && (bool) old('children.0.copy_kept')),
                            returnable: @js((bool) old('children.0.is_returnable')),
                        }"
                        class="space-y-4"
                    >
                        @csrf

                        <input type="hidden" name="children[0][routing_mode]" value="child">
                        @if (! $canUseAdvancedRouting)
                            <input type="hidden" name="children[0][forward_version_type]" value="original">
                        @endif

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <x-input-label for="child_subject" :value="__('Child Subject')" />
                                <x-text-input
                                    id="child_subject"
                                    name="children[0][subject]"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :value="old('children.0.subject', $parentDocument->subject)"
                                    required
                                />
                                <x-input-error :messages="$errors->get('children.0.subject')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="child_document_type" :value="__('Document Type')" />
                                <select
                                    id="child_document_type"
                                    name="children[0][document_type]"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="communication" @selected(old('children.0.document_type', $parentDocument->document_type) === 'communication')>Communication</option>
                                    <option value="submission" @selected(old('children.0.document_type', $parentDocument->document_type) === 'submission')>Submission</option>
                                    <option value="request" @selected(old('children.0.document_type', $parentDocument->document_type) === 'request')>Request</option>
                                    <option value="for_processing" @selected(old('children.0.document_type', $parentDocument->document_type) === 'for_processing')>For Processing</option>
                                </select>
                                <x-input-error :messages="$errors->get('children.0.document_type')" class="mt-2" />
                            </div>

                            <div>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                    <input
                                        type="checkbox"
                                        name="children[0][same_owner_as_parent]"
                                        value="1"
                                        x-model="sameOwner"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Same owner as parent
                                </label>
                            </div>
                            <div x-show="!sameOwner" x-cloak>
                                <x-input-label for="child_owner_type" :value="__('Owner Type')" />
                                <select
                                    id="child_owner_type"
                                    name="children[0][owner_type]"
                                    x-bind:disabled="sameOwner"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="district" @selected(old('children.0.owner_type', $parentDocument->owner_type) === 'district')>District</option>
                                    <option value="school" @selected(old('children.0.owner_type', $parentDocument->owner_type) === 'school')>School</option>
                                    <option value="personal" @selected(old('children.0.owner_type', $parentDocument->owner_type) === 'personal')>Personal</option>
                                    <option value="others" @selected(old('children.0.owner_type', $parentDocument->owner_type) === 'others')>Others</option>
                                </select>
                                <x-input-error :messages="$errors->get('children.0.owner_type')" class="mt-2" />
                            </div>

                            <div x-show="!sameOwner" x-cloak>
                                <x-input-label for="child_owner_name" :value="__('Owner Name')" />
                                <x-text-input
                                    id="child_owner_name"
                                    name="children[0][owner_name]"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :value="old('children.0.owner_name', $parentDocument->owner_name)"
                                    x-bind:disabled="sameOwner"
                                />
                                <x-input-error :messages="$errors->get('children.0.owner_name')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @if ($canUseAdvancedRouting)
                                <div>
                                    <x-input-label for="child_forward_version_type" :value="__('Forward Version')" />
                                    <select
                                        id="child_forward_version_type"
                                        name="children[0][forward_version_type]"
                                        x-model="forwardVersion"
                                        @change="if (forwardVersion === 'original' && destinationIds.length > 1) { destinationIds = [destinationIds[0]]; } if (forwardVersion !== 'original') { copyKept = false; }"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="original">Original</option>
                                        <option value="certified_copy">Certified Copy</option>
                                        <option value="photocopy">Photocopy</option>
                                        <option value="scan">Digital Scan</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('children.0.forward_version_type')" class="mt-2" />
                                </div>
                            @else
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                    Basic mode: routing uses <span class="font-semibold text-gray-800">Original</span> document version.
                                </div>
                            @endif

                            <div>
                                <x-input-label for="child_remarks" :value="__('Remarks (Optional)')" />
                                <textarea
                                    id="child_remarks"
                                    name="children[0][remarks]"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >{{ old('children.0.remarks') }}</textarea>
                                <x-input-error :messages="$errors->get('children.0.remarks')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label :value="$canUseAdvancedRouting ? __('Route To Departments') : __('Route To')" />
                            @if ($canUseAdvancedRouting)
                                <p class="mt-1 text-xs text-gray-500">
                                    Original version can route to one department only. Use copy versions for multi-department routing.
                                </p>
                                <div class="mt-2 grid grid-cols-1 gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 md:grid-cols-2">
                                    @foreach ($activeDepartments as $department)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                name="children[0][to_department_ids][]"
                                                value="{{ $department->id }}"
                                                x-model="destinationIds"
                                                :disabled="forwardVersion === 'original' && !destinationIds.includes('{{ (string) $department->id }}') && destinationIds.length >= 1"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            >
                                            {{ $department->name }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <select
                                    name="children[0][to_department_ids][]"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Select Department</option>
                                    @foreach ($activeDepartments as $department)
                                        <option value="{{ $department->id }}" @selected($oldPrimaryDestinationId === (string) $department->id)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <x-input-error :messages="$errors->get('children.0.to_department_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('children.0.to_department_ids.*')" class="mt-2" />
                        </div>

                        @if ($canUseAdvancedRouting)
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-3" x-show="forwardVersion === 'original'" x-cloak>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                    <input
                                        type="checkbox"
                                        name="children[0][copy_kept]"
                                        value="1"
                                        x-model="copyKept"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Keep a photocopy in source department
                                </label>

                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2" x-show="copyKept" x-cloak>
                                    <div>
                                        <x-input-label for="child_copy_storage_location" :value="__('Copy Storage Location')" />
                                        <x-text-input
                                            id="child_copy_storage_location"
                                            name="children[0][copy_storage_location]"
                                            type="text"
                                            class="mt-1 block w-full"
                                            :value="old('children.0.copy_storage_location')"
                                            placeholder="e.g. Cabinet B-2"
                                        />
                                        <x-input-error :messages="$errors->get('children.0.copy_storage_location')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="child_copy_purpose" :value="__('Copy Purpose (Optional)')" />
                                        <x-text-input
                                            id="child_copy_purpose"
                                            name="children[0][copy_purpose]"
                                            type="text"
                                            class="mt-1 block w-full"
                                            :value="old('children.0.copy_purpose')"
                                            placeholder="e.g. Audit reference"
                                        />
                                        <x-input-error :messages="$errors->get('children.0.copy_purpose')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-md border border-indigo-200 bg-indigo-50 p-3" x-show="forwardVersion !== 'original'" x-cloak>
                                <x-input-label for="child_original_storage_location" :value="__('Original Storage Location')" class="text-indigo-900" />
                                <x-text-input
                                    id="child_original_storage_location"
                                    name="children[0][original_storage_location]"
                                    type="text"
                                    class="mt-1 block w-full md:w-2/3"
                                    :value="old('children.0.original_storage_location')"
                                    placeholder="e.g. Records Vault Shelf A"
                                />
                                <p class="mt-1 text-xs text-indigo-700">Required when forwarding copy versions.</p>
                                <x-input-error :messages="$errors->get('children.0.original_storage_location')" class="mt-2" />
                            </div>
                        @endif

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                <input
                                    type="checkbox"
                                    name="children[0][is_returnable]"
                                    value="1"
                                    x-model="returnable"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                Returnable original document
                            </label>

                            <div class="mt-3" x-show="returnable" x-cloak>
                                <x-input-label for="child_return_deadline" :value="__('Return Deadline')" />
                                <x-text-input
                                    id="child_return_deadline"
                                    name="children[0][return_deadline]"
                                    type="date"
                                    class="mt-1 block w-full md:w-64"
                                    :value="old('children.0.return_deadline')"
                                />
                                <x-input-error :messages="$errors->get('children.0.return_deadline')" class="mt-2" />
                            </div>
                        </div>

                        @if ($canUseAdvancedRouting)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <label class="inline-flex items-start gap-2 text-sm text-amber-900">
                                    <input
                                        type="checkbox"
                                        name="confirm_routing_reviewed"
                                        value="1"
                                        class="mt-0.5 rounded border-amber-300 text-indigo-600 focus:ring-indigo-500"
                                        @checked(old('confirm_routing_reviewed'))
                                        required
                                    >
                                    I reviewed the child document details before submitting.
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="confirm_routing_reviewed" value="1">
                        @endif

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                            <a
                                href="{{ route('documents.queues.index') }}"
                                class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                            >
                                Cancel
                            </a>
                            <x-primary-button>{{ __('Create Child') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
