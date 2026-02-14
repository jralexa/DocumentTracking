@php
    use App\UserRole;
    use Illuminate\Support\Facades\Route;

    $user = Auth::user();

    $canProcessDocuments = $user->canProcessDocuments();
    $canViewDocuments = $user->canViewDocuments();
    $canExportReports = $user->canExportReports();
    $isAdmin = $user->hasRole(UserRole::Admin);
    $isAdminOrManager = $user->hasAnyRole([UserRole::Admin, UserRole::Manager]);
    $showDashboard = $user->hasAnyRole([UserRole::Admin, UserRole::Manager, UserRole::Regular]);

    $dashboardRoute = Route::has('dashboard') ? route('dashboard') : null;
    $createDocumentRoute = Route::has('documents.create') ? route('documents.create') : null;
    $queueRoute = Route::has('documents.queues.index') ? route('documents.queues.index') : null;
    $monthlyReportRoute = Route::has('reports.departments.monthly') ? route('reports.departments.monthly') : null;
    $adminDepartmentsRoute = Route::has('admin.departments.index') ? route('admin.departments.index') : null;
    $adminUsersRoute = Route::has('admin.users.index') ? route('admin.users.index') : null;
    $adminRolePermissionsRoute = Route::has('admin.roles-permissions.index') ? route('admin.roles-permissions.index') : null;

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

    if ($canProcessDocuments) {
        $documentItems[] = ['label' => 'Register Document', 'href' => $createDocumentRoute, 'active' => request()->routeIs('documents.create')];
        $documentItems[] = ['label' => 'Process Documents', 'href' => $queueRoute, 'active' => request()->routeIs('documents.queues.*')];
    }

    $documentItems[] = ['label' => 'Track Document', 'href' => null, 'active' => false];

    if ($canViewDocuments) {
        $documentItems[] = [
            'label' => $isAdminOrManager ? 'Document List / Search' : 'Document List / Search (View Only)',
            'href' => null,
            'active' => false,
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
                ['label' => 'Create Case', 'href' => null, 'active' => false],
                ['label' => 'Case List', 'href' => null, 'active' => false],
            ],
        ];
    }

    if ($canProcessDocuments) {
        $sections[] = [
            'title' => 'Custody & Copies',
            'items' => [
                ['label' => 'Original Custody', 'href' => null, 'active' => false],
                ['label' => 'Copy Inventory', 'href' => null, 'active' => false],
                ['label' => 'Returnable Documents', 'href' => $isAdminOrManager ? null : null, 'active' => false, 'hidden' => ! $isAdminOrManager],
            ],
        ];
    }

    if ($canExportReports) {
        $sections[] = [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Monthly Department Report', 'href' => $monthlyReportRoute, 'active' => request()->routeIs('reports.departments.monthly')],
                ['label' => 'Aging / Overdue Report', 'href' => null, 'active' => false],
                ['label' => 'Performance Report', 'href' => null, 'active' => false],
                ['label' => 'Custody Report', 'href' => null, 'active' => false],
            ],
        ];
    }

    if ($isAdmin) {
        $sections[] = [
            'title' => 'Administration',
            'items' => [
                ['label' => 'Departments', 'href' => $adminDepartmentsRoute, 'active' => request()->routeIs('admin.departments.*')],
                ['label' => 'Users', 'href' => $adminUsersRoute, 'active' => request()->routeIs('admin.users.*')],
                ['label' => 'Roles / Permissions', 'href' => $adminRolePermissionsRoute, 'active' => request()->routeIs('admin.roles-permissions.*')],
                ['label' => 'System Logs', 'href' => null, 'active' => false],
                ['label' => 'Settings', 'href' => null, 'active' => false],
            ],
        ];
    }
@endphp

<div class="space-y-5">
    @foreach ($sections as $section)
        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $section['title'] }}</p>
            <div class="mt-2 space-y-1">
                @foreach ($section['items'] as $item)
                    @continue(($item['hidden'] ?? false) === true)

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
    @endforeach
</div>
