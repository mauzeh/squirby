<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Telemetry Dashboard — Squirby</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        .text-2xs { font-size: 0.65rem; line-height: 0.9rem; }
        /* grid-template-rows collapse for session blocks (iOS-safe, per feature-workflow) */
        .collapsible { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 200ms ease; }
        .collapsible.open { grid-template-rows: 1fr; }
        .collapsible > div { overflow: hidden; }
    </style>

    <script>
        window.telemetryRoutes = {
            summary: "{{ route('telemetry.summary') }}",
            blueprint: "{{ route('telemetry.blueprint') }}",
            device: "{{ route('telemetry.device') }}",
            trail: "{{ route('telemetry.trail') }}"
        };
    </script>
    <script src="{{ asset('js/telemetry/dashboard.js') }}"></script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen antialiased">
    <div x-data="telemetryDashboard()" x-init="init()" class="max-w-md mx-auto min-h-screen flex flex-col">

        <!-- ============================= SCREEN 1: OVERVIEW ============================= -->
        <div x-show="view === 'overview'" class="flex flex-col p-4 space-y-5">

            <!-- Header -->
            <header class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Telemetry</h1>
                    <p class="text-xs text-slate-400">Anonymous analytics</p>
                </div>
                <div class="flex items-center space-x-2">
                    <span x-show="loading" class="flex items-center space-x-1 text-2xs text-blue-400">
                        <svg class="animate-spin h-3 w-3 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span class="text-2xs uppercase tracking-wider bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full">Overview</span>
                </div>
            </header>

            <!-- Date filter -->
            @include('telemetry.partials._date-filter')

            <!-- Aggregate Data Age Label -->
            <div x-show="formatDataAge()" class="text-2xs text-slate-500 text-right font-mono -mb-2" x-text="formatDataAge()"></div>

            <!-- Total Card 1: Devices -->
            @include('telemetry.partials._devices-card')

            <!-- Total Card 2: Blueprint -->
            @include('telemetry.partials._blueprint-card')

            <!-- Total Card 3: Dwell -->
            @include('telemetry.partials._dwell-card')

            <!-- Device List -->
            @include('telemetry.partials._device-list')
        </div>

        <!-- ============================= SCREEN 2: DEVICE DEEP-DIVE ============================= -->
        @include('telemetry.partials._deep-dive')

    </div>
</body>
</html>
