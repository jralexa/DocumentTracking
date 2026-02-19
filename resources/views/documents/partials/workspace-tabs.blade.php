@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $canIntakeDocuments = $user?->canIntakeDocuments() ?? false;
    $canProcessDocuments = $user?->canProcessDocuments() ?? false;
    $canViewDocuments = $user?->canViewDocuments() ?? false;

    $tabs = [
        [
            'label' => 'Add Document',
            'href' => Route::has('documents.create') ? route('documents.create') : null,
            'visible' => $canIntakeDocuments,
            'active' => request()->routeIs('documents.create'),
        ],
        [
            'label' => 'Process Documents',
            'href' => Route::has('documents.queues.index') ? route('documents.queues.index') : null,
            'visible' => $canProcessDocuments,
            'active' => request()->routeIs('documents.queues.*') || request()->routeIs('documents.split.*'),
        ],
        [
            'label' => 'Track Document',
            'href' => Route::has('documents.track') ? route('documents.track') : null,
            'visible' => true,
            'active' => request()->routeIs('documents.track'),
        ],
    ];

    $visibleTabs = array_values(array_filter($tabs, static fn (array $tab): bool => (bool) $tab['visible']));
    $showMonitorFilters = request()->routeIs('documents.track') || request()->routeIs('documents.index');
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm">
    <nav class="flex flex-wrap items-center gap-2" aria-label="Documents Menu Tabs">
        @foreach ($visibleTabs as $tab)
            @if ($tab['href'] !== null)
                <a
                    href="{{ $tab['href'] }}"
                    @class([
                        'inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide transition',
                        'bg-slate-800 text-white' => $tab['active'],
                        'border border-gray-300 text-gray-700 hover:bg-slate-50' => ! $tab['active'],
                    ])
                >
                    {{ $tab['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    @if ($showMonitorFilters)
        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-2">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Document View</span>
            <a
                href="{{ route('documents.track') }}"
                @class([
                    'inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide transition',
                    'bg-indigo-600 text-white' => request()->routeIs('documents.track'),
                    'border border-gray-300 text-gray-700 hover:bg-slate-50' => ! request()->routeIs('documents.track'),
                ])
            >
                Track
            </a>
            @if ($canViewDocuments)
                <a
                    href="{{ route('documents.index') }}"
                    @class([
                        'inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide transition',
                        'bg-indigo-600 text-white' => request()->routeIs('documents.index'),
                        'border border-gray-300 text-gray-700 hover:bg-slate-50' => ! request()->routeIs('documents.index'),
                    ])
                >
                    Document List
                </a>
            @endif
        </div>
    @endif
</section>
