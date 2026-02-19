<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Case List') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @include('documents.partials.monitor-tabs')

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Case Number</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Title</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Owner</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Documents</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($cases as $case)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $case->case_number }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $case->title }}</p>
                                        <p class="text-xs text-gray-500">{{ ucfirst($case->priority) }} priority</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $case->owner_name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ str_replace('_', ' ', ucfirst($case->status)) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $case->documents_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a
                                            href="{{ route('cases.show', $case) }}"
                                            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
                                        >
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No cases found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($cases->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3">
                        {{ $cases->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
