<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit Department
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $department->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="abbreviation" :value="__('Abbreviation (Optional)')" />
                        <x-text-input id="abbreviation" name="abbreviation" type="text" class="mt-1 block w-full" :value="old('abbreviation', $department->abbreviation)" />
                        <p class="mt-1 text-xs text-gray-500">Department code is managed internally.</p>
                        <x-input-error :messages="$errors->get('abbreviation')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $department->is_active)) />
                        <span class="text-sm text-gray-700">Active department</span>
                    </label>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <a href="{{ route('admin.organization.index', ['tab' => 'departments']) }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-100">
                            Cancel
                        </a>
                        <x-primary-button>Update Department</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
