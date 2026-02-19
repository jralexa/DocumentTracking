@php
    $globalSearchRoute = route('search.global');
    $globalSearchSuggestionsRoute = route('search.suggestions');
@endphp

<nav class="relative z-40">
    <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-100 bg-white/95 px-4 backdrop-blur lg:px-8">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 lg:hidden"
                aria-label="Open menu"
            >
                Menu
            </button>
            <button
                type="button"
                x-show="sidebarCollapsed"
                x-cloak
                @click="sidebarCollapsed = false"
                class="hidden items-center rounded-md border border-slate-300 bg-white p-2 text-slate-700 transition hover:bg-slate-100 lg:inline-flex"
                aria-label="Show sidebar"
                title="Show sidebar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 011.06 0l4 4a.75.75 0 010 1.06l-4 4a.75.75 0 11-1.06-1.06L10.69 10 7.22 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
        </div>

        <form
            method="GET"
            action="{{ $globalSearchRoute }}"
            class="mx-3 hidden min-w-0 max-w-xl flex-1 lg:block"
            x-data="{
                query: @js(request('q', '')),
                suggestions: [],
                isOpen: false,
                debounceTimer: null,
                isLoading: false,
                fetchSuggestions() {
                    if (this.debounceTimer) {
                        clearTimeout(this.debounceTimer);
                    }

                    this.debounceTimer = setTimeout(async () => {
                        const term = this.query.trim();
                        if (term.length === 0) {
                            this.suggestions = [];
                            this.isOpen = false;
                            return;
                        }

                        this.isLoading = true;
                        const url = new URL(@js($globalSearchSuggestionsRoute), window.location.origin);
                        url.searchParams.set('q', term);

                        try {
                            const response = await fetch(url.toString(), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                            const payload = await response.json();
                            this.suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
                            this.isOpen = this.suggestions.length > 0;
                        } catch (error) {
                            this.suggestions = [];
                            this.isOpen = false;
                        } finally {
                            this.isLoading = false;
                        }
                    }, 180);
                },
                goToSuggestion(item) {
                    window.location.assign(item.href);
                },
                closeSuggestions() {
                    setTimeout(() => {
                        this.isOpen = false;
                    }, 120);
                }
            }"
            @keydown.escape.window="isOpen = false"
        >
            <label for="global_search" class="sr-only">Global Search</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 014.398 8.804l3.649 3.648a.75.75 0 11-1.06 1.061l-3.648-3.649A5.5 5.5 0 119 3.5zM5 9a4 4 0 108 0 4 4 0 00-8 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input
                    id="global_search"
                    type="search"
                    name="q"
                    x-model="query"
                    placeholder="Search pages, menus, tracking, subjects, owners, cases..."
                    class="block w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-24 text-sm text-slate-700 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    autocomplete="off"
                    @focus="if (suggestions.length > 0) { isOpen = true; }"
                    @blur="closeSuggestions()"
                    @input="fetchSuggestions()"
                >
                <button
                    type="submit"
                    class="absolute inset-y-0 right-0 inline-flex items-center rounded-r-md border-l border-slate-300 px-3 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50"
                >
                    Search
                </button>
                <div
                    x-cloak
                    x-show="isOpen"
                    class="absolute left-0 right-0 top-[calc(100%+0.4rem)] z-50 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
                >
                    <div class="max-h-80 overflow-y-auto py-1">
                        <template x-for="(item, index) in suggestions" :key="`${item.type}-${index}-${item.href}`">
                            <a
                                :href="item.href"
                                class="block px-3 py-2 transition hover:bg-slate-50"
                                @mousedown.prevent="goToSuggestion(item)"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-800" x-text="item.label"></p>
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                                        x-text="item.type"
                                    ></span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-slate-500" x-text="item.description"></p>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </form>

        <a
            href="{{ $globalSearchRoute }}"
            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-100 lg:hidden"
        >
            Search
        </a>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-slate-600 transition duration-150 ease-in-out hover:text-slate-800 focus:outline-none">
                    <div>{{ Auth::user()->name }}</div>
                    <div class="ms-1">
                        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    Profile
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Logout
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>

    <div
        x-show="sidebarOpen"
        x-cloak
        class="fixed inset-0 bg-slate-900/40 lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col overflow-hidden bg-white shadow-sm transition-transform duration-200"
        :class="{
            'translate-x-0': sidebarOpen,
            'lg:translate-x-0': !sidebarCollapsed,
            'lg:-translate-x-full': sidebarCollapsed
        }"
    >
        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="text-sm font-semibold text-slate-800">{{ config('app.name') }}</span>
            </a>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="sidebarCollapsed = true"
                    class="hidden items-center rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:inline-flex"
                    aria-label="Hide sidebar"
                    title="Hide sidebar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 01-1.06 0l-4-4a.75.75 0 010-1.06l4-4a.75.75 0 111.06 1.06L9.31 10l3.47 3.47a.75.75 0 010 1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    class="inline-flex items-center rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                >
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto border-r border-t border-slate-100 bg-white p-4">
            @include('layouts.sidebar-links')
        </div>

    </aside>
</nav>
