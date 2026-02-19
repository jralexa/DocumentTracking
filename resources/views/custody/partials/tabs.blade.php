@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $canProcessDocuments = $user?->canProcessDocuments() ?? false;
    $canManageDocuments = $user?->canManageDocuments() ?? false;

    $tabs = [
        [
            'label' => 'Original Custody',
            'href' => Route::has('custody.originals.index') ? route('custody.originals.index') : null,
            'visible' => $canProcessDocuments,
            'active' => request()->routeIs('custody.originals.*'),
        ],
        [
            'label' => 'Copy Inventory',
            'href' => Route::has('custody.copies.index') ? route('custody.copies.index') : null,
            'visible' => $canProcessDocuments,
            'active' => request()->routeIs('custody.copies.*'),
        ],
        [
            'label' => 'Returnable Documents',
            'href' => Route::has('custody.returnables.index') ? route('custody.returnables.index') : null,
            'visible' => $canManageDocuments,
            'active' => request()->routeIs('custody.returnables.*'),
        ],
    ];

    $visibleTabs = array_values(array_filter($tabs, static fn (array $tab): bool => (bool) $tab['visible'] && $tab['href'] !== null));
@endphp

@if ($visibleTabs !== [])
    <section class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
        <nav class="flex flex-wrap gap-2" aria-label="Custody Tabs">
            @foreach ($visibleTabs as $tab)
                <a
                    href="{{ $tab['href'] }}"
                    @class([
                        'inline-flex items-center rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition',
                        'bg-slate-900 text-white' => $tab['active'],
                        'border border-gray-300 text-gray-700 hover:bg-gray-100' => ! $tab['active'],
                    ])
                >
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </section>
@endif
