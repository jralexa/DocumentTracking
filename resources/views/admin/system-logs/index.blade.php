<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            System Logs
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-3 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-slate-200 bg-white p-3 text-slate-800 shadow-sm">
                <form method="GET" action="{{ route('admin.system-logs.index') }}" class="grid grid-cols-1 gap-2 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <x-input-label for="q" value="Search" class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full border-slate-300 bg-slate-50 text-xs font-mono text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500" :value="$filters['q'] ?? ''" placeholder="msg, action, route..." />
                    </div>

                    <div>
                        <x-input-label for="category" value="Category" class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500" />
                        <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 bg-slate-50 text-xs font-mono text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="level" value="Level" class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500" />
                        <select id="level" name="level" class="mt-1 block w-full rounded-md border-slate-300 bg-slate-50 text-xs font-mono text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">All</option>
                            <option value="info" @selected(($filters['level'] ?? '') === 'info')>info</option>
                            <option value="warning" @selected(($filters['level'] ?? '') === 'warning')>warning</option>
                            <option value="error" @selected(($filters['level'] ?? '') === 'error')>error</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="date_from" value="From" class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full border-slate-300 bg-slate-50 text-xs font-mono text-slate-800 focus:border-sky-500 focus:ring-sky-500" :value="$filters['date_from'] ?? ''" />
                    </div>

                    <div>
                        <x-input-label for="date_to" value="To" class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full border-slate-300 bg-slate-50 text-xs font-mono text-slate-800 focus:border-sky-500 focus:ring-sky-500" :value="$filters['date_to'] ?? ''" />
                    </div>

                    <div class="flex items-end gap-2 md:col-span-6">
                        <x-primary-button class="h-9 bg-sky-600 px-3 text-[11px] font-bold uppercase tracking-[0.16em] text-white hover:bg-sky-500">Filter</x-primary-button>
                        <a href="{{ route('admin.system-logs.index') }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700 hover:bg-slate-100">Reset</a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-800 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 font-mono text-[11px] leading-4">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">TS</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">USR</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">CAT</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">ACT</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">MSG</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">ROUTE/PATH</th>
                                <th class="px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($logs as $log)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-700">{{ $log->occurred_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td class="max-w-40 truncate px-2 py-2 text-slate-700" title="{{ $log->user?->name ?? 'System' }}">{{ $log->user?->name ?? 'System' }}</td>
                                    <td class="px-2 py-2">
                                        <span class="inline-flex rounded border border-slate-300 bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em] text-sky-700">{{ $log->category }}</span>
                                    </td>
                                    <td class="max-w-40 truncate px-2 py-2 text-slate-700" title="{{ $log->action }}">{{ $log->action }}</td>
                                    <td class="max-w-md truncate px-2 py-2 text-slate-800" title="{{ $log->message }}">{{ $log->message }}</td>
                                    <td class="max-w-sm px-2 py-2 text-[10px] text-slate-500">
                                        <div class="truncate" title="{{ $log->route_name ?? '-' }}">{{ $log->route_name ?? '-' }}</div>
                                        <div class="truncate text-slate-400" title="{{ $log->request_path ?? '-' }}">{{ $log->request_path ?? '-' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-slate-500">{{ $log->ip_address ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center font-mono text-xs text-slate-500">No logs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-3 py-2">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
