<nav class="relative z-40">
    <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-8">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 lg:hidden"
            >
                Menu
            </button>
            <x-application-logo class="block h-8 w-auto fill-current text-slate-800" />
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
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <x-application-logo class="block h-8 w-auto fill-current text-slate-800" />
                <span class="text-sm font-semibold text-slate-800">{{ config('app.name') }}</span>
            </a>
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

        <div class="flex-1 overflow-y-auto p-4">
            @include('layouts.sidebar-links')
        </div>

    </aside>
</nav>
