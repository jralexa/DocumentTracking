<section class="overflow-hidden rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
    <nav class="flex flex-wrap gap-2" aria-label="Report tabs">
        @php
            $tabs = [
                ['label' => 'Monthly', 'route' => 'reports.departments.monthly', 'active' => request()->routeIs('reports.departments.monthly')],
                ['label' => 'SLA', 'route' => 'reports.sla-compliance', 'active' => request()->routeIs('reports.sla-compliance')],
                ['label' => 'Aging', 'route' => 'reports.aging-overdue', 'active' => request()->routeIs('reports.aging-overdue')],
                ['label' => 'Performance', 'route' => 'reports.performance', 'active' => request()->routeIs('reports.performance')],
                ['label' => 'Custody', 'route' => 'reports.custody', 'active' => request()->routeIs('reports.custody')],
            ];
        @endphp

        @foreach ($tabs as $tab)
            <a
                href="{{ route($tab['route']) }}"
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
