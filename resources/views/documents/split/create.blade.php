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

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent Document</h3>
                <div class="mt-2 grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-3">
                    <p><span class="font-semibold text-gray-900">Tracking:</span> {{ $parentDocument->tracking_number }}</p>
                    <p><span class="font-semibold text-gray-900">Subject:</span> {{ $parentDocument->subject }}</p>
                    <p><span class="font-semibold text-gray-900">Current Department:</span> {{ $parentDocument->currentDepartment?->name ?? '-' }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <form
                    method="POST"
                    action="{{ route('documents.split.store', $parentDocument) }}"
                    class="space-y-4 p-4"
                    x-data="{
                        children: [
                            { subject: '', document_type: 'submission', same_owner_as_parent: true, owner_type: @js($parentDocument->owner_type), owner_name: @js($parentDocument->owner_name), forward_version_type: 'original', to_department_ids: [], copy_kept: false, copy_storage_location: '', copy_purpose: '', original_storage_location: '', is_returnable: false, return_deadline: '', remarks: '' }
                        ],
                        addChild() {
                            if (this.children.length >= 10) {
                                return;
                            }
                            this.children.push({
                                subject: '',
                                document_type: 'submission',
                                same_owner_as_parent: true,
                                owner_type: @js($parentDocument->owner_type),
                                owner_name: @js($parentDocument->owner_name),
                                forward_version_type: 'original',
                                to_department_ids: [],
                                copy_kept: false,
                                copy_storage_location: '',
                                copy_purpose: '',
                                original_storage_location: '',
                                is_returnable: false,
                                return_deadline: '',
                                remarks: ''
                            });
                        },
                        removeChild(index) {
                            if (this.children.length === 1) {
                                return;
                            }
                            this.children.splice(index, 1);
                        }
                    }"
                >
                    @csrf

                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Child Documents</h3>
                        <p class="text-xs text-gray-500">Auto suffix starts from: {{ $nextSuffix }}</p>
                        <button
                            type="button"
                            @click="addChild"
                            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                        >
                            Add Child
                        </button>
                    </div>

                    <template x-for="(child, index) in children" :key="index">
                        <article class="rounded-lg border border-gray-200 p-3">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    Child Row <span x-text="index + 1"></span>
                                </h4>
                                <button
                                    type="button"
                                    @click="removeChild(index)"
                                    class="text-xs font-semibold uppercase tracking-wide text-red-600"
                                >
                                    Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        x-model="child.subject"
                                        x-bind:name="'children[' + index + '][subject]'"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Document Type</label>
                                    <select x-bind:name="'children[' + index + '][document_type]'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="child.document_type" required>
                                        <option value="communication">Communication</option>
                                        <option value="submission">Submission</option>
                                        <option value="request">Request</option>
                                        <option value="for_processing">For Processing</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                        <input
                                            :name="'children[' + index + '][same_owner_as_parent]'"
                                            type="checkbox"
                                            value="1"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            x-model="child.same_owner_as_parent"
                                        >
                                        Same as parent owner
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Owner Type</label>
                                    <select
                                        x-bind:name="'children[' + index + '][owner_type]'"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        x-model="child.owner_type"
                                        :disabled="child.same_owner_as_parent"
                                        :required="!child.same_owner_as_parent"
                                    >
                                        <option value="district">District</option>
                                        <option value="school">School</option>
                                        <option value="personal">Personal</option>
                                        <option value="others">Others</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Owner Name</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        x-model="child.owner_name"
                                        x-bind:name="'children[' + index + '][owner_name]'"
                                        :disabled="child.same_owner_as_parent"
                                        :required="!child.same_owner_as_parent"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Forward Version</label>
                                    <select
                                        :name="'children[' + index + '][forward_version_type]'"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        x-model="child.forward_version_type"
                                    >
                                        <option value="original">Original</option>
                                        <option value="certified_copy">Certified Copy</option>
                                        <option value="photocopy">Photocopy</option>
                                        <option value="scan">Digital Scan</option>
                                    </select>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Route To Departments</label>
                                    <div class="mt-1 grid grid-cols-1 gap-2 rounded-md border border-gray-200 bg-gray-50 p-2 md:grid-cols-2">
                                        @foreach ($activeDepartments as $department)
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    :name="'children[' + index + '][to_department_ids][]'"
                                                    value="{{ $department->id }}"
                                                    x-model="child.to_department_ids"
                                                >
                                                {{ $department->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Action Taken / Remarks (Optional)</label>
                                    <textarea x-bind:name="'children[' + index + '][remarks]'" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="child.remarks"></textarea>
                                </div>

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-2 md:col-span-3">
                                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                        <input
                                            :name="'children[' + index + '][copy_kept]'"
                                            type="checkbox"
                                            value="1"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            x-model="child.copy_kept"
                                        >
                                        Keep a photocopy in source department
                                    </label>
                                    <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2" x-show="child.copy_kept">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Copy Storage Location</label>
                                            <input
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                x-model="child.copy_storage_location"
                                                :name="'children[' + index + '][copy_storage_location]'"
                                                placeholder="e.g. Cabinet B-2"
                                            >
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Copy Purpose (Optional)</label>
                                            <input
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                x-model="child.copy_purpose"
                                                :name="'children[' + index + '][copy_purpose]'"
                                                placeholder="e.g. Audit reference"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-md border border-indigo-200 bg-indigo-50 p-2 md:col-span-3" x-show="child.forward_version_type !== 'original'">
                                    <label class="block text-sm font-medium text-indigo-900">Original Storage Location (Required when forwarding copy)</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:w-2/3"
                                        x-model="child.original_storage_location"
                                        :name="'children[' + index + '][original_storage_location]'"
                                        placeholder="e.g. Records Vault Shelf A"
                                    >
                                    <p class="mt-1 text-xs text-indigo-700">When forwarding a copy, the original remains in source custody.</p>
                                </div>

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-2 md:col-span-3">
                                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                        <input x-bind:name="'children[' + index + '][is_returnable]'" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="child.is_returnable">
                                        Returnable original document
                                    </label>
                                    <div class="mt-2" x-show="child.is_returnable">
                                        <label class="block text-sm font-medium text-gray-700">Return Deadline</label>
                                        <input
                                            type="date"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:w-64"
                                            x-model="child.return_deadline"
                                            x-bind:name="'children[' + index + '][return_deadline]'"
                                        >
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                        <a
                            href="{{ route('documents.queues.index') }}"
                            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                        >
                            Cancel
                        </a>
                        <x-primary-button>
                            {{ __('Split and Route Children') }}
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
