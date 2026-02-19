<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto w-full max-w-5xl">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">
                {{ __('Add Document') }}
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
                @php
                    $canUseAdvancedEncoding = auth()->user()?->hasAnyRole([\App\UserRole::Admin, \App\UserRole::Manager]) ?? false;
                    $intakePrefill = is_array($intakePrefill ?? null) ? $intakePrefill : [];
                    $defaultQuickMode = (string) old('quick_mode', (string) ($intakePrefill['quick_mode'] ?? '1'));
                @endphp
                <form
                    method="POST"
                    action="{{ route('documents.store') }}"
                    enctype="multipart/form-data"
                    class="space-y-4 p-4 sm:p-5"
                    x-data="{
                        canUseAdvancedEncoding: @js($canUseAdvancedEncoding),
                        advancedMode: @js($canUseAdvancedEncoding ? $defaultQuickMode !== '1' : false),
                        caseMode: @js(old('case_mode', 'new')),
                        documentCaseId: @js(old('document_case_id', $intakePrefill['document_case_id'] ?? null)),
                        prefillCaseId: @js($intakePrefill['preferred_case_id'] ?? null),
                        prefillCaseLabel: @js($intakePrefill['preferred_case_label'] ?? null),
                        openCasePayloads: @js($openCasePayloads),
                        isReturnable: @js((bool) old('is_returnable')),
                        ownerType: @js(old('owner_type', $intakePrefill['owner_type'] ?? 'others')),
                        ownerName: @js(old('owner_name', $intakePrefill['owner_name'] ?? null)),
                        ownerReference: @js(old('owner_reference', $intakePrefill['owner_reference'] ?? null)),
                        caseTitle: @js(old('case_title')),
                        selectedDistrictId: @js(old('owner_district_id', $intakePrefill['owner_district_id'] ?? null)),
                        selectedSchoolId: @js(old('owner_school_id', $intakePrefill['owner_school_id'] ?? null)),
                        schools: @js($schools),
                        applyExistingCaseDefaults() {
                            if (this.caseMode !== 'existing' || !this.documentCaseId) {
                                return;
                            }

                            const payload = this.openCasePayloads[String(this.documentCaseId)] ?? null;
                            if (!payload) {
                                return;
                            }

                            this.ownerType = payload.owner_type ?? this.ownerType;
                            this.ownerName = payload.owner_name ?? this.ownerName;
                            this.ownerReference = payload.owner_reference ?? this.ownerReference;
                            this.caseTitle = payload.case_title ?? this.caseTitle;
                            this.selectedDistrictId = payload.owner_district_id ? String(payload.owner_district_id) : '';
                            this.selectedSchoolId = payload.owner_school_id ? String(payload.owner_school_id) : '';
                        },
                        useLastOpenCase() {
                            if (!this.prefillCaseId) {
                                return;
                            }

                            if (!this.openCasePayloads[String(this.prefillCaseId)]) {
                                return;
                            }

                            this.caseMode = 'existing';
                            this.documentCaseId = String(this.prefillCaseId);
                            this.applyExistingCaseDefaults();
                        },
                        get filteredSchools() {
                            if (!this.selectedDistrictId) {
                                return [];
                            }

                            return this.schools.filter((school) => String(school.district_id) === String(this.selectedDistrictId));
                        }
                    }"
                    x-effect="
                        if (!canUseAdvancedEncoding) { advancedMode = false; }
                        if (!advancedMode) { isReturnable = false; }
                        if (caseMode === 'existing') { applyExistingCaseDefaults(); }
                    "
                >
                    @csrf
                    <input type="hidden" name="quick_mode" :value="advancedMode ? 0 : 1">

                    <section class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-900">Encoding View</h3>
                                <p class="mt-1 text-xs text-indigo-700">
                                    Start with basic intake fields. Switch to advanced only when extra compliance and tracking metadata are needed.
                                </p>
                            </div>
                            @if ($canUseAdvancedEncoding)
                                <button
                                    type="button"
                                    @click="advancedMode = !advancedMode"
                                    class="inline-flex items-center rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                                    :class="advancedMode ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-500' : 'border-indigo-300 bg-white text-indigo-800 hover:bg-indigo-100'"
                                >
                                    <span x-text="advancedMode ? 'Advanced Encoding' : 'Basic Intake'"></span>
                                </button>
                            @else
                                <span class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-800">
                                    Basic Intake
                                </span>
                            @endif
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
                                    @change="if (caseMode === 'existing') { applyExistingCaseDefaults(); }"
                                >
                                    <option value="new">Create New Case</option>
                                    <option value="existing">Link to Existing Case</option>
                                </select>
                                <div x-show="caseMode === 'new' && prefillCaseId && openCasePayloads[String(prefillCaseId)]" x-cloak class="mt-2">
                                    <button
                                        type="button"
                                        @click="useLastOpenCase()"
                                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-700 hover:bg-indigo-100"
                                    >
                                        Use Last Open Case
                                    </button>
                                    <p class="mt-1 text-xs text-slate-500" x-text="prefillCaseLabel ? 'Last case: ' + prefillCaseLabel : 'Last encoded case is still open.'"></p>
                                </div>
                            </div>

                            <div x-show="caseMode === 'existing'" x-cloak>
                                <x-input-label for="document_case_id" :value="__('Open Case')" />
                                <select
                                    id="document_case_id"
                                    name="document_case_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="documentCaseId"
                                    @change="applyExistingCaseDefaults()"
                                    :required="caseMode === 'existing'"
                                >
                                    <option value="">Select Open Case</option>
                                    @foreach ($openCases as $openCase)
                                        <option value="{{ $openCase->id }}" @selected((string) old('document_case_id', $intakePrefill['document_case_id'] ?? null) === (string) $openCase->id)>
                                            {{ $openCase->case_number }} - {{ $openCase->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Owner fields auto-fill from selected case. Only open cases are listed.</p>
                                <x-input-error :messages="$errors->get('document_case_id')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Document Information</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="md:col-span-2" x-show="advancedMode" x-cloak>
                                <x-input-label for="case_title" :value="__('Case Title (Optional)')" />
                                <x-text-input id="case_title" name="case_title" type="text" class="mt-1 block w-full" x-model="caseTitle" />
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
                                    <option value="communication" @selected(old('document_type', $intakePrefill['document_type'] ?? null) === 'communication')>Communication</option>
                                    <option value="submission" @selected(old('document_type', $intakePrefill['document_type'] ?? null) === 'submission')>Submission</option>
                                    <option value="request" @selected(old('document_type', $intakePrefill['document_type'] ?? null) === 'request')>Request</option>
                                    <option value="for_processing" @selected(old('document_type', $intakePrefill['document_type'] ?? 'for_processing') === 'for_processing')>For Processing</option>
                                </select>
                                <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                            </div>

                            <div x-show="advancedMode" x-cloak>
                                <x-input-label for="reference_number" :value="__('Reference Number (Optional)')" />
                                <x-text-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full" :value="old('reference_number')" />
                                <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/40 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Owner Information</h3>
                        <template x-if="caseMode === 'existing'">
                            <div class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800">
                                Owner fields are linked to the selected case.
                            </div>
                        </template>
                        <template x-if="caseMode === 'existing'">
                            <div>
                                <input type="hidden" name="owner_type" :value="ownerType">
                                <input type="hidden" name="owner_name" :value="ownerName">
                                <input type="hidden" name="owner_reference" :value="ownerReference">
                                <input type="hidden" name="owner_district_id" :value="selectedDistrictId">
                                <input type="hidden" name="owner_school_id" :value="selectedSchoolId">
                            </div>
                        </template>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="owner_type" :value="__('Owner Type')" />
                                <select
                                    id="owner_type"
                                    name="owner_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="ownerType"
                                    :disabled="caseMode === 'existing'"
                                    required
                                >
                                    <option value="district" @selected(old('owner_type', $intakePrefill['owner_type'] ?? null) === 'district')>District</option>
                                    <option value="school" @selected(old('owner_type', $intakePrefill['owner_type'] ?? null) === 'school')>School</option>
                                    <option value="personal" @selected(old('owner_type', $intakePrefill['owner_type'] ?? null) === 'personal')>Personal</option>
                                    <option value="others" @selected(old('owner_type', $intakePrefill['owner_type'] ?? 'others') === 'others')>Others</option>
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
                                    :disabled="caseMode === 'existing'"
                                    :required="ownerType === 'district' || ownerType === 'school'"
                                    @change="if (ownerType === 'school' && !filteredSchools.some((school) => String(school.id) === String(selectedSchoolId))) { selectedSchoolId = ''; }"
                                >
                                    <option value="">Select District</option>
                                    @foreach ($districts as $district)
                                        <option value="{{ $district->id }}" @selected((string) old('owner_district_id', $intakePrefill['owner_district_id'] ?? null) === (string) $district->id)>{{ $district->name }}</option>
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
                                    :disabled="caseMode === 'existing' || !selectedDistrictId"
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
                                <x-text-input id="owner_name" name="owner_name" type="text" class="mt-1 block w-full" x-model="ownerName" x-bind:disabled="caseMode === 'existing'" />
                                <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2" x-show="advancedMode" x-cloak>
                                <x-input-label for="owner_reference" :value="__('Owner Reference (Optional)')" />
                                <x-text-input id="owner_reference" name="owner_reference" type="text" class="mt-1 block w-full" x-model="ownerReference" x-bind:disabled="caseMode === 'existing'" />
                                <x-input-error :messages="$errors->get('owner_reference')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <details class="rounded-lg border border-slate-200 p-4" x-show="canUseAdvancedEncoding && advancedMode" x-cloak @if($errors->has('priority') || $errors->has('due_at')) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-700">Workflow Settings</summary>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="priority" :value="__('Priority')" />
                                <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :required="advancedMode">
                                    <option value="low" @selected(old('priority', $intakePrefill['priority'] ?? null) === 'low')>Low</option>
                                    <option value="normal" @selected(old('priority', $intakePrefill['priority'] ?? 'normal') === 'normal')>Normal</option>
                                    <option value="high" @selected(old('priority', $intakePrefill['priority'] ?? null) === 'high')>High</option>
                                    <option value="urgent" @selected(old('priority', $intakePrefill['priority'] ?? null) === 'urgent')>Urgent</option>
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

                    <details class="rounded-lg border border-slate-200 p-4" x-show="canUseAdvancedEncoding && advancedMode" x-cloak @if($errors->has('source_channel') || $errors->has('document_classification') || $errors->has('routing_slip_number') || $errors->has('control_number') || $errors->has('received_by_name') || $errors->has('received_at') || $errors->has('sla_days')) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-700">Compliance Details (Optional)</summary>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <x-input-label for="source_channel" :value="__('Source Channel')" />
                                <select id="source_channel" name="source_channel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="walk_in" @selected(old('source_channel', $intakePrefill['source_channel'] ?? 'walk_in') === 'walk_in')>Walk-in</option>
                                    <option value="email" @selected(old('source_channel', $intakePrefill['source_channel'] ?? null) === 'email')>Email</option>
                                    <option value="courier" @selected(old('source_channel', $intakePrefill['source_channel'] ?? null) === 'courier')>Courier</option>
                                    <option value="system" @selected(old('source_channel', $intakePrefill['source_channel'] ?? null) === 'system')>System</option>
                                </select>
                                <x-input-error :messages="$errors->get('source_channel')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="document_classification" :value="__('Classification')" />
                                <select id="document_classification" name="document_classification" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="routine" @selected(old('document_classification', $intakePrefill['document_classification'] ?? 'routine') === 'routine')>Routine</option>
                                    <option value="urgent" @selected(old('document_classification', $intakePrefill['document_classification'] ?? null) === 'urgent')>Urgent</option>
                                    <option value="confidential" @selected(old('document_classification', $intakePrefill['document_classification'] ?? null) === 'confidential')>Confidential</option>
                                </select>
                                <x-input-error :messages="$errors->get('document_classification')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="sla_days" :value="__('SLA Days (Optional)')" />
                                <x-text-input id="sla_days" name="sla_days" type="number" min="1" max="90" class="mt-1 block w-full" :value="old('sla_days', $intakePrefill['sla_days'] ?? null)" />
                                <x-input-error :messages="$errors->get('sla_days')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="received_at" :value="__('Received At (Optional)')" />
                                <x-text-input id="received_at" name="received_at" type="datetime-local" class="mt-1 block w-full" :value="old('received_at')" />
                                <x-input-error :messages="$errors->get('received_at')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="routing_slip_number" :value="__('Routing Slip # (Optional)')" />
                                <x-text-input id="routing_slip_number" name="routing_slip_number" type="text" class="mt-1 block w-full" :value="old('routing_slip_number', $intakePrefill['routing_slip_number'] ?? null)" />
                                <x-input-error :messages="$errors->get('routing_slip_number')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="control_number" :value="__('Control Number (Optional)')" />
                                <x-text-input id="control_number" name="control_number" type="text" class="mt-1 block w-full" :value="old('control_number', $intakePrefill['control_number'] ?? null)" />
                                <x-input-error :messages="$errors->get('control_number')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="received_by_name" :value="__('Received By (Optional)')" />
                                <x-text-input id="received_by_name" name="received_by_name" type="text" class="mt-1 block w-full" :value="old('received_by_name', $intakePrefill['received_by_name'] ?? null)" />
                                <x-input-error :messages="$errors->get('received_by_name')" class="mt-2" />
                            </div>
                        </div>
                    </details>

                    <details class="rounded-lg border border-slate-200 p-4" x-show="canUseAdvancedEncoding && advancedMode" x-cloak @if($errors->has('description') || $errors->has('initial_remarks') || $errors->has('item_name')) open @endif>
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

                            <div>
                                <x-input-label for="attachments" :value="__('Attachments (Optional)')" />
                                <input
                                    id="attachments"
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <p class="mt-1 text-xs text-slate-500">Up to 10 files, max 10MB each. Allowed: PDF, image, Word, Excel.</p>
                                <x-input-error :messages="$errors->get('attachments')" class="mt-2" />
                                <x-input-error :messages="$errors->get('attachments.*')" class="mt-2" />
                            </div>
                        </div>
                    </details>

                    <details class="rounded-lg border border-slate-200 p-4" x-show="canUseAdvancedEncoding && advancedMode" x-cloak @if($errors->has('return_deadline')) open @endif>
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
                        @php
                            $cancelRoute = auth()->user()?->canProcessDocuments()
                                ? route('documents.queues.index')
                                : (auth()->user()?->canViewDocuments() ? route('documents.index') : route('dashboard'));
                        @endphp
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ $cancelRoute }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-100">
                                {{ __('Cancel') }}
                            </a>
                            <button
                                type="submit"
                                name="add_another"
                                value="1"
                                class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-700 transition hover:bg-indigo-50"
                            >
                                {{ __('Record & Add Another') }}
                            </button>
                            <x-primary-button type="submit" name="add_another" value="0">
                                {{ __('Record Document') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </section>
    </div>
</x-app-layout>
