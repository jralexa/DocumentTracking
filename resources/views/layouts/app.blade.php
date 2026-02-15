<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <x-flash-toast :message="session('status')" type="success" />
        <x-flash-toast :message="session('intake_notice')" type="info" />

        <div
            x-data="{ sidebarOpen: false, sidebarCollapsed: false }"
            :class="sidebarCollapsed ? 'lg:pl-0' : 'lg:pl-72'"
            class="min-h-screen bg-slate-100 transition-all duration-200"
        >
            @include('layouts.navigation')

            @isset($header)
                <header class="pt-6">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="pb-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
