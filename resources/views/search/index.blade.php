<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Global Search
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('search.global') }}" class="grid grid-cols-1 items-end gap-3 md:grid-cols-6">
                    <div class="md:col-span-5">
                        <x-input-label for="q" :value="__('Search')" />
                        <x-text-input
                            id="q"
                            name="q"
                            type="search"
                            class="mt-1 block w-full"
                            :value="$query"
                            placeholder="Search pages, tracking, subjects, owners, cases..."
                            required
                        />
                    </div>
                    <x-primary-button class="justify-center">Search</x-primary-button>
                </form>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Pages and Menus</h3>
                    </header>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($pages as $page)
                            <li class="px-4 py-3">
                                <a href="{{ $page['href'] }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                                    {{ $page['label'] }}
                                </a>
                                <p class="mt-1 text-xs text-gray-500">{{ $page['description'] }}</p>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-sm text-gray-500">No matching pages found.</li>
                        @endforelse
                    </ul>
                </article>

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Documents</h3>
                    </header>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($documents as $document)
                            <li class="px-4 py-3">
                                <a
                                    href="{{ route('documents.track', ['tracking_number' => $document->metadata['display_tracking'] ?? $document->tracking_number]) }}"
                                    class="text-sm font-semibold text-indigo-700 hover:text-indigo-800"
                                >
                                    {{ $document->metadata['display_tracking'] ?? $document->tracking_number }}
                                </a>
                                <p class="mt-1 text-sm text-gray-800">{{ $document->subject }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $document->currentDepartment?->name ?? 'Unassigned' }} - {{ str_replace('_', ' ', ucfirst($document->status->value)) }}
                                </p>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-sm text-gray-500">No matching documents found.</li>
                        @endforelse
                    </ul>
                </article>

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <header class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">Cases</h3>
                    </header>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($cases as $case)
                            <li class="px-4 py-3">
                                <a href="{{ route('cases.show', $case) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                                    {{ $case->case_number }}
                                </a>
                                <p class="mt-1 text-sm text-gray-800">{{ $case->title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ ucfirst($case->status) }}</p>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-sm text-gray-500">No matching cases found.</li>
                        @endforelse
                    </ul>
                </article>
            </section>
        </div>
    </div>
</x-app-layout>
