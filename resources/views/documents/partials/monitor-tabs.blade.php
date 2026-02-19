@php
    use App\UserRole;
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $canViewDocuments = $user?->canViewDocuments() ?? false;
    $isAdminOrManager = $user?->hasAnyRole([UserRole::Admin, UserRole::Manager]) ?? false;

    $tabs = [
        [
            'label' => 'Track Document',
            'href' => Route::has('documents.track') ? route('documents.track') : null,
            'visible' => true,
            'active' => request()->routeIs('documents.track'),
        ],
        [
            'label' => 'Document List',
            'href' => Route::has('documents.index') ? route('documents.index') : null,
            'visible' => $canViewDocuments,
            'active' => request()->routeIs('documents.index'),
        ],
        [
            'label' => 'Case List',
            'href' => Route::has('cases.index') ? route('cases.index') : null,
            'visible' => $isAdminOrManager,
            'active' => request()->routeIs('cases.*'),
        ],
    ];

    $visibleTabs = array_values(array_filter($tabs, static fn (array $tab): bool => (bool) $tab['visible'] && $tab['href'] !== null));
@endphp

@if ($visibleTabs !== [])
    <section class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
        <nav class="flex flex-wrap gap-2" aria-label="Document Monitoring Tabs">
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
