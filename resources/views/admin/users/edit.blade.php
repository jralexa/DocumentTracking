<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit User
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
            @include('admin.users.partials.tabs')

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form
                    method="POST"
                    action="{{ route('admin.users.update', $managedUser) }}"
                    class="space-y-4"
                    x-data="{
                        selectedRole: @js(old('role', $managedUser->role->value)),
                        init() {
                            if (this.selectedRole === 'guest') {
                                this.$refs.departmentSelect.value = '';
                            }
                        }
                    }"
                >
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $managedUser->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $managedUser->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="role" :value="__('Role')" />
                            <select
                                id="role"
                                name="role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                x-model="selectedRole"
                                @change="if (selectedRole === 'guest') { $refs.departmentSelect.value = ''; }"
                                required
                            >
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(old('role', $managedUser->role->value) === $role->value)>{{ ucfirst($role->value) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('role')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="department_id" :value="__('Department (Optional)')" />
                            <select
                                id="department_id"
                                name="department_id"
                                x-ref="departmentSelect"
                                x-bind:disabled="selectedRole === 'guest'"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-500"
                            >
                                <option value="">No Department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('department_id', $managedUser->department_id) === (string) $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p x-cloak x-show="selectedRole === 'guest'" class="mt-1 text-xs text-amber-700">
                                Guest personnel accounts are intake-only and must not be assigned to a department.
                            </p>
                            <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                        </div>
                    </div>

                    @php
                        $departmentReassignmentBlockers = session('department_reassignment_blockers');
                    @endphp
                    @if (is_array($departmentReassignmentBlockers))
                        <section class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            <h3 class="font-semibold">Department Change Blocked</h3>
                            <p class="mt-1">
                                Resolve these items first, then retry department reassignment.
                            </p>

                            @if (! empty($departmentReassignmentBlockers['for_action_documents']))
                                <div class="mt-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide">For Action Documents</p>
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($departmentReassignmentBlockers['for_action_documents'] as $documentItem)
                                            <li>
                                                <a
                                                    href="{{ route('documents.track', ['tracking_number' => $documentItem['tracking_number']]) }}"
                                                    class="font-medium text-amber-900 underline decoration-amber-500 underline-offset-2 hover:text-amber-700"
                                                >
                                                    {{ $documentItem['tracking_number'] }}
                                                </a>
                                                <span class="text-amber-800">- {{ $documentItem['subject'] ?? 'No subject' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($departmentReassignmentBlockers['pending_outgoing_transfers']))
                                <div class="mt-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide">Pending Outgoing Transfers</p>
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($departmentReassignmentBlockers['pending_outgoing_transfers'] as $transferItem)
                                            <li>
                                                <a
                                                    href="{{ route('documents.track', ['tracking_number' => $transferItem['tracking_number']]) }}"
                                                    class="font-medium text-amber-900 underline decoration-amber-500 underline-offset-2 hover:text-amber-700"
                                                >
                                                    {{ $transferItem['tracking_number'] }}
                                                </a>
                                                <span class="text-amber-800">
                                                    - to {{ $transferItem['to_department'] ?? 'Unknown Department' }}
                                                    @if (! empty($transferItem['forwarded_at']))
                                                        ({{ $transferItem['forwarded_at'] }})
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </section>
                    @endif

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="password" :value="__('New Password (Optional)')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-100">
                            Cancel
                        </a>
                        <x-primary-button>Update User</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
