<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-900">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 px-6 py-8 shadow-sm sm:px-8">
                <div class="max-w-2xl space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-200">
                        Account Settings
                    </p>
                    <h1 class="text-2xl font-semibold text-white sm:text-3xl">
                        Manage your profile securely
                    </h1>
                    <p class="text-sm text-slate-200 sm:text-base">
                        Keep your account details up to date and maintain strong security settings.
                    </p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        @include('profile.partials.update-profile-information-form')
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        @include('profile.partials.update-password-form')
                    </article>
                </div>

                <aside class="space-y-6">
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        @include('profile.partials.delete-user-form')
                    </article>
                </aside>
            </section>
        </div>
    </div>
</x-app-layout>
