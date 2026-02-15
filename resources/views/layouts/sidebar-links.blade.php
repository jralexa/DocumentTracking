@php
    use App\UserRole;
    use Illuminate\Support\Facades\Route;

    $user = Auth::user();

    $canIntakeDocuments = $user->canIntakeDocuments();
    $canProcessDocuments = $user->canProcessDocuments();
    $canViewDocuments = $user->canViewDocuments();
    $canExportReports = $user->canExportReports();
    $isAdmin = $user->hasRole(UserRole::Admin);
    $isAdminOrManager = $user->hasAnyRole([UserRole::Admin, UserRole::Manager]);
    $showDashboard = $user->hasAnyRole([UserRole::Admin, UserRole::Manager, UserRole::Regular]);

    $dashboardRoute = Route::has('dashboard') ? route('dashboard') : null;
    $createDocumentRoute = Route::has('documents.create') ? route('documents.create') : null;
    $queueRoute = Route::has('documents.queues.index') ? route('documents.queues.index') : null;
    $trackDocumentRoute = Route::has('documents.track') ? route('documents.track') : null;
    $documentListRoute = Route::has('documents.index') ? route('documents.index') : null;
    $custodyOriginalsRoute = Route::has('custody.originals.index') ? route('custody.originals.index') : null;
    $custodyCopiesRoute = Route::has('custody.copies.index') ? route('custody.copies.index') : null;
    $custodyReturnablesRoute = Route::has('custody.returnables.index') ? route('custody.returnables.index') : null;
    $monthlyReportRoute = Route::has('reports.departments.monthly') ? route('reports.departments.monthly') : null;
    $reportsIndexRoute = Route::has('reports.index') ? route('reports.index') : $monthlyReportRoute;
    $casesIndexRoute = Route::has('cases.index') ? route('cases.index') : null;
    $adminOrganizationRoute = Route::has('admin.organization.index') ? route('admin.organization.index') : null;
    $adminUsersRoute = Route::has('admin.users.index') ? route('admin.users.index') : null;

    $sections = [];

    if ($showDashboard) {
        $sections[] = [
            'title' => 'Dashboard',
            'items' => [
                ['label' => 'Dashboard', 'href' => $dashboardRoute, 'active' => request()->routeIs('dashboard')],
            ],
        ];
    }

    $documentItems = [];

    if ($canIntakeDocuments) {
        $documentItems[] = ['label' => 'Receive and Record', 'href' => $createDocumentRoute, 'active' => request()->routeIs('documents.create')];
    }

    if ($canProcessDocuments) {
        $documentItems[] = ['label' => 'Route / Process', 'href' => $queueRoute, 'active' => request()->routeIs('documents.queues.*')];
    }

    $documentItems[] = ['label' => 'Track Document', 'href' => $trackDocumentRoute, 'active' => request()->routeIs('documents.track')];

    if ($canViewDocuments) {
        $documentItems[] = [
            'label' => $isAdminOrManager ? 'Document List / Search' : 'Document List / Search (View Only)',
            'href' => $documentListRoute,
            'active' => request()->routeIs('documents.index'),
        ];
    }

    if (count($documentItems) > 0) {
        $sections[] = [
            'title' => 'Documents',
            'items' => $documentItems,
        ];
    }

    if ($isAdminOrManager) {
        $sections[] = [
            'title' => 'Cases',
            'items' => [
                ['label' => 'Case List', 'href' => $casesIndexRoute, 'active' => request()->routeIs('cases.*')],
            ],
        ];
    }

    if ($canProcessDocuments) {
        $sections[] = [
            'title' => 'Custody & Copies',
            'items' => [
                ['label' => 'Original Custody', 'href' => $custodyOriginalsRoute, 'active' => request()->routeIs('custody.originals.*')],
                ['label' => 'Copy Inventory', 'href' => $custodyCopiesRoute, 'active' => request()->routeIs('custody.copies.*')],
                ['label' => 'Returnable Documents', 'href' => $custodyReturnablesRoute, 'active' => request()->routeIs('custody.returnables.*'), 'hidden' => ! $isAdminOrManager],
            ],
        ];
    }

    if ($canExportReports) {
        $sections[] = [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Reports', 'href' => $reportsIndexRoute, 'active' => request()->routeIs('reports.*')],
            ],
        ];
    }

    if ($isAdmin) {
        $sections[] = [
            'title' => 'Administration',
            'items' => [
                ['label' => 'Organization', 'href' => $adminOrganizationRoute, 'active' => request()->routeIs('admin.organization.*') || request()->routeIs('admin.departments.*') || request()->routeIs('admin.districts.*') || request()->routeIs('admin.schools.*')],
                ['label' => 'Users', 'href' => $adminUsersRoute, 'active' => request()->routeIs('admin.users.*') || request()->routeIs('admin.roles-permissions.*')],
                ['label' => 'System Logs', 'href' => null, 'active' => false],
                ['label' => 'Settings', 'href' => null, 'active' => false],
            ],
        ];
    }
@endphp

<div class="space-y-5">
    @foreach ($sections as $section)
        @php
            $visibleItems = array_values(array_filter($section['items'], static fn (array $item): bool => ($item['hidden'] ?? false) !== true));
            $sectionHasActive = collect($visibleItems)->contains(static fn (array $item): bool => (bool) ($item['active'] ?? false));
            $isSingleItemSection = count($visibleItems) === 1;
        @endphp

        @if ($isSingleItemSection)
            @php $singleItem = $visibleItems[0]; @endphp
            <div class="rounded-lg border border-transparent">
                @if (strtolower($section['title']) !== 'dashboard')
                    <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $section['title'] }}</p>
                @endif
                <div @class([
                    'mt-2 space-y-1',
                    'ml-3 border-l border-slate-200 pl-3' => strtolower($section['title']) !== 'dashboard',
                ])>
                    @if ($singleItem['href'])
                        <a
                            href="{{ $singleItem['href'] }}"
                            @click="sidebarOpen = false"
                            @class([
                                'block rounded-md px-3 py-2 text-sm font-medium transition',
                                'bg-slate-900 text-white' => $singleItem['active'],
                                'text-slate-700 hover:bg-slate-100' => ! $singleItem['active'],
                            ])
                        >
                            {{ $singleItem['label'] }}
                        </a>
                    @else
                        <span class="flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-slate-400">
                            {{ $singleItem['label'] }}
                            <span class="text-[10px] uppercase tracking-wide">Soon</span>
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div x-data="{ open: @js($sectionHasActive) }" class="rounded-lg border border-transparent">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    :aria-expanded="open"
                >
                    <span>{{ $section['title'] }}</span>
                    <svg
                        class="h-4 w-4 text-slate-400 transition-transform"
                        :class="open ? 'rotate-180' : 'rotate-0'"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    class="mt-2 ml-3 space-y-1 border-l border-slate-200 pl-3"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                >
                    @foreach ($visibleItems as $item)
                        @if ($item['href'])
                            <a
                                href="{{ $item['href'] }}"
                                @click="sidebarOpen = false"
                                @class([
                                    'block rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-slate-900 text-white' => $item['active'],
                                    'text-slate-700 hover:bg-slate-100' => ! $item['active'],
                                ])
                            >
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span class="flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-slate-400">
                                {{ $item['label'] }}
                                <span class="text-[10px] uppercase tracking-wide">Soon</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
