<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Organization Management
        </h2>
    </x-slot>

    @php
        $tabs = [
            'departments' => 'Departments',
            'districts' => 'Districts',
            'schools' => 'Schools',
        ];

        $addRoute = match ($tab) {
            'districts' => route('admin.districts.create'),
            'schools' => route('admin.schools.create'),
            default => route('admin.departments.create'),
        };

        $addLabel = match ($tab) {
            'districts' => 'Add District',
            'schools' => 'Add School',
            default => 'Add Department',
        };
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-600">Manage departments, districts, and schools from one page.</p>
                    </div>
                    <a href="{{ $addRoute }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-indigo-700">
                        {{ $addLabel }}
                    </a>
                </div>

                <div class="mb-5 border-b border-gray-200">
                    <nav class="-mb-px flex gap-5" aria-label="Organization Tabs">
                        @foreach ($tabs as $key => $label)
                            <a
                                href="{{ route('admin.organization.index', ['tab' => $key]) }}"
                                @class([
                                    'border-b-2 px-1 pb-2 text-sm font-medium transition',
                                    'border-indigo-600 text-indigo-700' => $tab === $key,
                                    'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-700' => $tab !== $key,
                                ])
                            >
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="overflow-x-auto rounded-md border border-gray-200">
                    @if ($tab === 'departments')
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Abbreviation</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Users</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($records as $department)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $department->code }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $department->name }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $department->abbreviation ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $department->users_count }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-green-100 text-green-700' => $department->is_active,
                                                'bg-gray-100 text-gray-700' => ! $department->is_active,
                                            ])>
                                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('admin.departments.edit', $department) }}" class="text-indigo-600 hover:text-indigo-700">Edit</a>
                                                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-700">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No departments found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @elseif ($tab === 'districts')
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Schools</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($records as $district)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $district->code }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $district->name }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $district->schools_count }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-green-100 text-green-700' => $district->is_active,
                                                'bg-gray-100 text-gray-700' => ! $district->is_active,
                                            ])>
                                                {{ $district->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('admin.districts.edit', $district) }}" class="text-indigo-600 hover:text-indigo-700">Edit</a>
                                                <form method="POST" action="{{ route('admin.districts.destroy', $district) }}" onsubmit="return confirm('Delete this district?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-700">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No districts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">District</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">School Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($records as $school)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">{{ $school->district?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $school->code ?? '-' }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $school->name }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-green-100 text-green-700' => $school->is_active,
                                                'bg-gray-100 text-gray-700' => ! $school->is_active,
                                            ])>
                                                {{ $school->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('admin.schools.edit', $school) }}" class="text-indigo-600 hover:text-indigo-700">Edit</a>
                                                <form method="POST" action="{{ route('admin.schools.destroy', $school) }}" onsubmit="return confirm('Delete this school?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-700">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No schools found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="mt-4">
                    {{ $records->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
