<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">School Management</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm text-gray-600">Manage school master data used by owner selection under each district.</p>
                    <a href="{{ route('admin.schools.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-indigo-700">
                        Add School
                    </a>
                </div>

                <div class="overflow-x-auto rounded-md border border-gray-200">
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
                            @forelse ($schools as $school)
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
                </div>

                <div class="mt-4">
                    {{ $schools->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
