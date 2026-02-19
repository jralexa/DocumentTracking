<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Original Custody') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @include('custody.partials.tabs')

            @if ($errors->has('release_original'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first('release_original') }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('custody.originals.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <x-input-label for="q" :value="__('Search')" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filters['q']" placeholder="Tracking, subject, owner..." />
                    </div>
                    <div>
                        <x-input-label for="department_id" :value="__('Department')" />
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            @foreach ($activeDepartments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <a href="{{ route('custody.originals.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50">Reset</a>
                        <x-primary-button>{{ __('Search') }}</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Tracking</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Subject</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Department</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Custodian</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Location</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Received</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($records as $record)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->document->metadata['display_tracking'] ?? $record->document->tracking_number }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $record->document->subject }}</p>
                                        <p class="text-xs text-gray-500">{{ $record->document->owner_name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->department?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->user?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->physical_location ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->received_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if (auth()->user()?->department_id === $record->department_id)
                                            <details class="inline-block text-left">
                                                <summary class="cursor-pointer rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50">
                                                    Release Original
                                                </summary>
                                                <div class="mt-2 w-96 rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
                                                    <form method="POST" action="{{ route('custody.originals.release', $record->document) }}" class="space-y-2">
                                                        @csrf
                                                        <div>
                                                            <x-input-label :value="__('Route Original To (Optional)')" />
                                                            <select
                                                                name="to_department_id"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            >
                                                                <option value="">No department routing</option>
                                                                @foreach ($activeDepartments as $department)
                                                                    @if ($department->id !== $record->department_id)
                                                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                            <p class="mt-1 text-xs text-gray-500">Choose a destination only when the original must continue internal routing.</p>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Release To (Required if no routing department)')" />
                                                            <x-text-input
                                                                name="release_to"
                                                                type="text"
                                                                class="mt-1 block w-full text-sm"
                                                                placeholder="e.g. Document owner / external recipient"
                                                            />
                                                        </div>
                                                        <div class="rounded-md border border-gray-200 bg-gray-50 p-2">
                                                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                                                <input
                                                                    type="checkbox"
                                                                    name="copy_kept"
                                                                    value="1"
                                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                >
                                                                Keep photocopy in this office
                                                            </label>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Copy Storage Location (if kept)')" />
                                                            <x-text-input
                                                                name="copy_storage_location"
                                                                type="text"
                                                                class="mt-1 block w-full text-sm"
                                                                placeholder="e.g. Records Cabinet A-1"
                                                            />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Original Storage Location (Destination)')" />
                                                            <x-text-input
                                                                name="original_storage_location"
                                                                type="text"
                                                                class="mt-1 block w-full text-sm"
                                                                placeholder="e.g. Budget Cabinet C-1"
                                                            />
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Copy Purpose (Optional)')" />
                                                            <textarea
                                                                name="copy_purpose"
                                                                rows="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                placeholder="e.g. Office reference"
                                                            ></textarea>
                                                        </div>
                                                        <div>
                                                            <x-input-label :value="__('Remarks (Optional)')" />
                                                            <textarea
                                                                name="remarks"
                                                                rows="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            ></textarea>
                                                        </div>
                                                        <x-primary-button class="w-full justify-center text-xs">
                                                            Release Original
                                                        </x-primary-button>
                                                    </form>
                                                </div>
                                            </details>
                                        @else
                                            <span class="text-xs text-gray-400">No action</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No original custody records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($records->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3">
                        {{ $records->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
