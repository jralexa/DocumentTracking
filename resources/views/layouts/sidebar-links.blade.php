@php
    use App\UserRole;
    use Illuminate\Support\Facades\Route;

    $user = Auth::user();

    $canIntakeDocuments = $user->canIntakeDocuments();
    $canProcessDocuments = $user->canProcessDocuments();
    $canViewDocuments = $user->canViewDocuments();
    $canManageDocuments = $user->canManageDocuments();
    $canExportReports = $user->canExportReports();
    $isAdmin = $user->hasRole(UserRole::Admin);

    $createDocumentRoute = Route::has('documents.create') ? route('documents.create') : null;
    $queueRoute = Route::has('documents.queues.index') ? route('documents.queues.index') : null;
    $trackDocumentRoute = Route::has('documents.track') ? route('documents.track') : null;
    $documentListRoute = Route::has('documents.index') ? route('documents.index') : null;
    $casesIndexRoute = Route::has('cases.index') ? route('cases.index') : null;
    $custodyOriginalsRoute = Route::has('custody.originals.index') ? route('custody.originals.index') : null;
    $custodyCopiesRoute = Route::has('custody.copies.index') ? route('custody.copies.index') : null;
    $custodyReturnablesRoute = Route::has('custody.returnables.index') ? route('custody.returnables.index') : null;
    $monthlyReportRoute = Route::has('reports.departments.monthly') ? route('reports.departments.monthly') : null;
    $slaComplianceReportRoute = Route::has('reports.sla-compliance') ? route('reports.sla-compliance') : null;
    $agingOverdueReportRoute = Route::has('reports.aging-overdue') ? route('reports.aging-overdue') : null;
    $performanceReportRoute = Route::has('reports.performance') ? route('reports.performance') : null;
    $custodyReportRoute = Route::has('reports.custody') ? route('reports.custody') : null;
    $adminOrganizationRoute = Route::has('admin.organization.index') ? route('admin.organization.index') : null;
    $adminUsersRoute = Route::has('admin.users.index') ? route('admin.users.index') : null;
    $adminSystemLogsRoute = Route::has('admin.system-logs.index') ? route('admin.system-logs.index') : null;

    $sections = [];

    $documentItems = [];
    if ($canIntakeDocuments && $createDocumentRoute !== null) {
        $documentItems[] = ['label' => 'Add Document', 'href' => $createDocumentRoute, 'active' => request()->routeIs('documents.create')];
    }
    if ($canProcessDocuments && $queueRoute !== null) {
        $documentItems[] = ['label' => 'Process Documents', 'href' => $queueRoute, 'active' => request()->routeIs('documents.queues.*') || request()->routeIs('documents.split.*')];
    }
    $monitorRoute = $trackDocumentRoute ?? $documentListRoute ?? $casesIndexRoute;
    if ($monitorRoute !== null) {
        $documentItems[] = [
            'label' => 'Monitoring',
            'href' => $monitorRoute,
            'active' => request()->routeIs('documents.track') || request()->routeIs('documents.index') || request()->routeIs('cases.*'),
        ];
    }
    $custodyWorkspaceRoute = $custodyOriginalsRoute ?? $custodyCopiesRoute ?? $custodyReturnablesRoute;
    $canAccessCustodyWorkspace = ($canProcessDocuments && ($custodyOriginalsRoute !== null || $custodyCopiesRoute !== null))
        || ($canManageDocuments && $custodyReturnablesRoute !== null);
    if ($canAccessCustodyWorkspace && $custodyWorkspaceRoute !== null) {
        $documentItems[] = [
            'label' => 'Custody',
            'href' => $custodyWorkspaceRoute,
            'active' => request()->routeIs('custody.*'),
        ];
    }

    if ($documentItems !== []) {
        $sections[] = [
            'title' => 'Documents',
            'items' => $documentItems,
        ];
    }

    $keyElementItems = [];
    if ($isAdmin && $adminOrganizationRoute !== null) {
        $keyElementItems[] = [
            'label' => 'Organization',
            'href' => route('admin.organization.index', ['tab' => 'departments']),
            'active' => request()->routeIs('admin.organization.*') || request()->routeIs('admin.departments.*') || request()->routeIs('admin.districts.*') || request()->routeIs('admin.schools.*'),
        ];
    }
    if ($isAdmin && $adminUsersRoute !== null) {
        $keyElementItems[] = ['label' => 'User Management', 'href' => $adminUsersRoute, 'active' => request()->routeIs('admin.users.*') || request()->routeIs('admin.roles-permissions.*')];
    }
    if ($isAdmin && $adminSystemLogsRoute !== null) {
        $keyElementItems[] = ['label' => 'System Logs', 'href' => $adminSystemLogsRoute, 'active' => request()->routeIs('admin.system-logs.*')];
    }

    if ($keyElementItems !== []) {
        $sections[] = [
            'title' => 'Key Elements',
            'items' => $keyElementItems,
        ];
    }

    $analyticsItems = [];
    $analyticsWorkspaceRoute = $monthlyReportRoute
        ?? $slaComplianceReportRoute
        ?? $agingOverdueReportRoute
        ?? $performanceReportRoute
        ?? $custodyReportRoute;
    if ($canExportReports && $analyticsWorkspaceRoute !== null) {
        $analyticsItems[] = [
            'label' => 'Reports',
            'href' => $analyticsWorkspaceRoute,
            'active' => request()->routeIs('reports.*'),
        ];
    }

    if ($analyticsItems !== []) {
        $sections[] = [
            'title' => 'Analytics',
            'items' => $analyticsItems,
        ];
    }
@endphp

<div class="space-y-5">
    @foreach ($sections as $section)
        @php
            $visibleItems = $section['items'];
            $sectionHasActive = collect($visibleItems)->contains(static fn (array $item): bool => (bool) ($item['active'] ?? false));
            $isSingleItemSection = count($visibleItems) === 1;
        @endphp

        @if ($isSingleItemSection)
            @php $singleItem = $visibleItems[0]; @endphp
            <div class="rounded-lg border border-transparent">
                <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $section['title'] }}</p>
                <div class="mt-2 ml-3 space-y-1 border-l border-slate-200 pl-3">
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
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
