<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit School</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('admin.schools.update', $school) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="district_id" :value="__('District')" />
                        <select id="district_id" name="district_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Select District</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected((string) old('district_id', $school->district_id) === (string) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('district_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="code" :value="__('Code (Optional)')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $school->code)" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('School Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $school->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $school->is_active)) />
                        <span class="text-sm text-gray-700">Active school</span>
                    </label>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <a href="{{ route('admin.organization.index', ['tab' => 'schools']) }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-100">
                            Cancel
                        </a>
                        <x-primary-button>Update School</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
