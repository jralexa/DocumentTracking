<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            User Management
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm text-gray-600">Create users per role/department so you can test role-based workflow behavior.</p>
                    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-indigo-700">
                        Add User
                    </a>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="overflow-x-auto rounded-md border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Role</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Department</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ ucfirst($user->role->value) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $user->department?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-700">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
