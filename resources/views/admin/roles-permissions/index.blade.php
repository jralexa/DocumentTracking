<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Roles &amp; Permissions
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <p class="mb-4 text-sm text-gray-600">Current permission matrix used by route and gate authorization.</p>

                <div class="overflow-x-auto rounded-md border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Capability</th>
                                @foreach ($roles as $role)
                                    <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ ucfirst($role->value) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($capabilities as $label => $capability)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">
                                        <div class="font-medium text-gray-900">{{ $label }}</div>
                                        <div class="text-xs text-gray-500">{{ $capability }}</div>
                                    </td>
                                    @foreach ($roles as $role)
                                        @php
                                            $allowed = in_array($capability, $roleCapabilities[$role->value] ?? [], true);
                                        @endphp
                                        <td class="px-4 py-3 text-center">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                                'bg-green-100 text-green-700' => $allowed,
                                                'bg-gray-100 text-gray-600' => ! $allowed,
                                            ])>
                                                {{ $allowed ? 'Allowed' : 'Blocked' }}
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
