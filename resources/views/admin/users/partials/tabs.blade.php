<section class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
    <nav class="flex flex-wrap gap-2" aria-label="User administration tabs">
        <a
            href="{{ route('admin.users.index') }}"
            @class([
                'inline-flex items-center rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition',
                'bg-slate-900 text-white' => request()->routeIs('admin.users.*'),
                'border border-gray-300 text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin.users.*'),
            ])
        >
            User Management
        </a>
        <a
            href="{{ route('admin.roles-permissions.index') }}"
            @class([
                'inline-flex items-center rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition',
                'bg-slate-900 text-white' => request()->routeIs('admin.roles-permissions.*'),
                'border border-gray-300 text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin.roles-permissions.*'),
            ])
        >
            Roles / Permissions
        </a>
    </nav>
</section>
