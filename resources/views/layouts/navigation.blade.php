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
                        Log Out
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
