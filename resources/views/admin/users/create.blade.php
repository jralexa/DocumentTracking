<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Create User
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
            @include('admin.users.partials.tabs')

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form
                    method="POST"
                    action="{{ route('admin.users.store') }}"
                    class="space-y-4"
                    x-data="{
                        selectedRole: @js(old('role', 'regular')),
                        init() {
                            if (this.selectedRole === 'guest') {
                                this.$refs.departmentSelect.value = '';
                            }
                        }
                    }"
                >
                    @csrf

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
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
                                    <option value="{{ $role->value }}" @selected(old('role', 'regular') === $role->value)>{{ ucfirst($role->value) }}</option>
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
                                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>
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

                    <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                        The system will automatically generate a temporary password and send it to the user's email.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-100">
                            Cancel
                        </a>
                        <x-primary-button>Create User</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
