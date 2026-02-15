<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto w-full max-w-5xl">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">
                {{ __('Receive and Record Document') }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-5xl">
            @if (session('intake_notice'))
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    {{ session('intake_notice') }}
                </div>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                <form
                    method="POST"
                    action="{{ route('documents.store') }}"
                    class="space-y-4 p-4 sm:p-5"
                    x-data="{
                        quickMode: @js(old('quick_mode', '1') === '1'),
                        caseMode: @js(old('case_mode', 'new')),
                        isReturnable: @js((bool) old('is_returnable')),
                        ownerType: @js(old('owner_type', 'others')),
                        selectedDistrictId: @js(old('owner_district_id')),
                        selectedSchoolId: @js(old('owner_school_id')),
                        schools: @js($schools),
                        get filteredSchools() {
                            if (!this.selectedDistrictId) {
                                return [];
                            }

                            return this.schools.filter((school) => String(school.district_id) === String(this.selectedDistrictId));
                        }
                    }"
                    x-effect="if (quickMode) { isReturnable = false; }"
                >
                    @csrf
                    <input type="hidden" name="quick_mode" :value="quickMode ? 1 : 0">

                    <section class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-900">Entry Mode</h3>
                                <p class="mt-1 text-xs text-indigo-700">Quick Add uses only core fields for fast intake. Turn off for full encoding.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-800">
                                <input type="checkbox" x-model="quickMode" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Quick Add
                            </label>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Case Assignment</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="case_mode" :value="__('Case Mode')" />
                                <select
                                    id="case_mode"
                                    name="case_mode"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="caseMode"
                                >
                                    <option value="new">Create New Case</option>
                                    <option value="existing">Link to Existing Case</option>
                                </select>
                            </div>

                            <div x-show="caseMode === 'existing'" x-cloak>
                                <x-input-label for="document_case_id" :value="__('Open Case')" />
                                <select
                                    id="document_case_id"
                                    name="document_case_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :required="caseMode === 'existing'"
                                >
                                    <option value="">Select Open Case</option>
                                    @foreach ($openCases as $openCase)
                                        <option value="{{ $openCase->id }}" @selected((string) old('document_case_id') === (string) $openCase->id)>
                                            {{ $openCase->case_number }} - {{ $openCase->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('document_case_id')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Document Information</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="md:col-span-2" x-show="!quickMode" x-cloak>
                                <x-input-label for="case_title" :value="__('Case Title (Optional)')" />
                                <x-text-input id="case_title" name="case_title" type="text" class="mt-1 block w-full" :value="old('case_title')" />
                                <x-input-error :messages="$errors->get('case_title')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="subject" :value="__('Document Subject')" />
                                <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" required autofocus />
                                <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="document_type" :value="__('Document Type')" />
                                <select id="document_type" name="document_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="communication" @selected(old('document_type') === 'communication')>Communication</option>
                                    <option value="submission" @selected(old('document_type') === 'submission')>Submission</option>
                                    <option value="request" @selected(old('document_type') === 'request')>Request</option>
                                    <option value="for_processing" @selected(old('document_type', 'for_processing') === 'for_processing')>For Processing</option>
                                </select>
                                <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                            </div>

                            <div x-show="!quickMode" x-cloak>
                                <x-input-label for="reference_number" :value="__('Reference Number (Optional)')" />
                                <x-text-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full" :value="old('reference_number')" />
                                <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Owner Information</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="owner_type" :value="__('Owner Type')" />
                                <select
                                    id="owner_type"
                                    name="owner_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="ownerType"
                                    required
                                >
                                    <option value="district" @selected(old('owner_type') === 'district')>District</option>
                                    <option value="school" @selected(old('owner_type') === 'school')>School</option>
                                    <option value="personal" @selected(old('owner_type') === 'personal')>Personal</option>
                                    <option value="others" @selected(old('owner_type', 'others') === 'others')>Others</option>
                                </select>
                                <x-input-error :messages="$errors->get('owner_type')" class="mt-2" />
                            </div>

                            <div x-show="ownerType === 'district' || ownerType === 'school'" x-cloak>
                                <x-input-label for="owner_district_id" :value="__('District')" />
                                <select
                                    id="owner_district_id"
                                    name="owner_district_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="selectedDistrictId"
                                    :required="ownerType === 'district' || ownerType === 'school'"
                                    @change="if (ownerType === 'school' && !filteredSchools.some((school) => String(school.id) === String(selectedSchoolId))) { selectedSchoolId = ''; }"
                                >
                                    <option value="">Select District</option>
                                    @foreach ($districts as $district)
                                        <option value="{{ $district->id }}" @selected((string) old('owner_district_id') === (string) $district->id)>{{ $district->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('owner_district_id')" class="mt-2" />
                            </div>

                            <div x-show="ownerType === 'school'" x-cloak>
                                <x-input-label for="owner_school_id" :value="__('School')" />
                                <select
                                    id="owner_school_id"
                                    name="owner_school_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="selectedSchoolId"
                                    :disabled="!selectedDistrictId"
                                    :required="ownerType === 'school'"
                                >
                                    <option value="">Select School</option>
                                    <template x-for="school in filteredSchools" :key="school.id">
                                        <option :value="school.id" x-text="school.name"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('owner_school_id')" class="mt-2" />
                            </div>

                            <div x-show="ownerType === 'personal' || ownerType === 'others'" x-cloak>
                                <x-input-label for="owner_name" :value="__('Owner Name')" />
                                <x-text-input id="owner_name" name="owner_name" type="text" class="mt-1 block w-full" :value="old('owner_name')" />
                                <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2" x-show="!quickMode" x-cloak>
                                <x-input-label for="owner_reference" :value="__('Owner Reference (Optional)')" />
                                <x-text-input id="owner_reference" name="owner_reference" type="text" class="mt-1 block w-full" :value="old('owner_reference')" />
                                <x-input-error :messages="$errors->get('owner_reference')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <details
                        class="rounded-lg border border-slate-200 p-4"
                        x-show="!quickMode"
                        x-cloak
                        @if($errors->has('priority') || $errors->has('due_at')) open @endif
                    >
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-700">Workflow Settings</summary>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="priority" :value="__('Priority')" />
                                <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :required="!quickMode">
                                    <option value="low" @selected(old('priority') === 'low')>Low</option>
                                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                    <option value="high" @selected(old('priority') === 'high')>High</option>
                                    <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="due_at" :value="__('Due Date (Optional)')" />
                                <x-text-input id="due_at" name="due_at" type="date" class="mt-1 block w-full" :value="old('due_at')" />
                                <x-input-error :messages="$errors->get('due_at')" class="mt-2" />
                            </div>
                        </div>
                    </details>

                    <details
                        class="rounded-lg border border-slate-200 p-4"
                        x-show="!quickMode"
                        x-cloak
                        @if($errors->has('description') || $errors->has('initial_remarks') || $errors->has('item_name')) open @endif
                    >
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-700">Additional Details</summary>
                        <div class="mt-3 grid grid-cols-1 gap-3">
                            <div>
                                <x-input-label for="description" :value="__('Description (Optional)')" />
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="item_name" :value="__('Main Item Name (Optional)')" />
                                <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" />
                                <x-input-error :messages="$errors->get('item_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="initial_remarks" :value="__('Initial Remarks (Optional)')" />
                                <textarea id="initial_remarks" name="initial_remarks" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('initial_remarks') }}</textarea>
                                <x-input-error :messages="$errors->get('initial_remarks')" class="mt-2" />
                            </div>
                        </div>
                    </details>

                    <details class="rounded-lg border border-slate-200 p-4" x-show="!quickMode" x-cloak @if($errors->has('return_deadline')) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-700">Original &amp; Return Settings</summary>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                                <input
                                    id="is_returnable"
                                    name="is_returnable"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    x-model="isReturnable"
                                    @checked(old('is_returnable'))
                                />
                                <div>
                                    <x-input-label for="is_returnable" :value="__('Returnable Original')" />
                                    <p class="text-xs text-slate-500">Enable when the original must be returned later.</p>
                                </div>
                            </div>

                            <div x-show="isReturnable" x-cloak>
                                <x-input-label for="return_deadline" :value="__('Return Deadline')" />
                                <x-text-input id="return_deadline" name="return_deadline" type="date" class="mt-1 block w-full" :value="old('return_deadline')" />
                                <x-input-error :messages="$errors->get('return_deadline')" class="mt-2" />
                            </div>
                        </div>
                    </details>

                    <div class="sticky bottom-0 z-10 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-5">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('documents.queues.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-100">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button>
                                {{ __('Record Document') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </section>
    </div>
</x-app-layout>
