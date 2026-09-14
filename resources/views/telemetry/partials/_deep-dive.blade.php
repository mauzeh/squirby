<!-- SCREEN 2: DEVICE DEEP-DIVE -->
<div x-show="view === 'device'" class="flex flex-col p-4 space-y-5" style="display: none;">

    <!-- Sticky back header -->
    <header class="sticky top-0 -mx-4 px-4 py-3 bg-slate-950/95 backdrop-blur border-b border-slate-800 flex items-center justify-between z-10">
        <button @click="showOverview()" class="text-sm text-blue-400 hover:underline flex items-center gap-1">&larr; Overview</button>
        <span class="text-2xs uppercase tracking-wider bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full">Device deep-dive</span>
    </header>

    <!-- Loading state -->
    <div x-show="deviceLoading" class="py-12 text-center text-xs text-blue-400 space-x-2">
        <svg class="animate-spin inline h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Loading device details…</span>
    </div>

    <div x-show="!deviceLoading && selectedDevice" class="space-y-5">
        <!-- Device header -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 shadow-lg">
            <div class="font-mono text-sm text-slate-200 break-all" id="ddDeviceId">
                <template x-if="selectedDeviceId === null">
                    <span class="text-xs font-mono font-semibold text-amber-400 bg-amber-950/50 border border-amber-800/50 px-1.5 py-0.5 rounded">no value</span>
                </template>
                <template x-if="selectedDeviceId !== null">
                    <span x-text="selectedDeviceId"></span>
                </template>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-slate-950 rounded-lg border border-slate-800 p-2">
                    <div class="text-lg font-bold text-white" x-text="selectedDevice?.stats?.session_count || 0">0</div>
                    <div class="text-2xs text-slate-500 uppercase tracking-wider">Sessions</div>
                </div>
                <div class="bg-slate-950 rounded-lg border border-slate-800 p-2">
                    <div class="text-lg font-bold text-white" x-text="selectedDevice?.stats?.event_count || 0">0</div>
                    <div class="text-2xs text-slate-500 uppercase tracking-wider">Events</div>
                </div>
                <div class="bg-slate-950 rounded-lg border border-slate-800 p-2">
                    <div class="text-lg font-bold text-white" x-text="selectedDevice?.stats?.engaged_formatted || '0s'">0s</div>
                    <div class="text-2xs text-slate-500 uppercase tracking-wider">Engaged</div>
                </div>
            </div>
            <div class="flex justify-between text-2xs text-slate-500">
                <span>First seen: <span x-text="formatDate(selectedDevice?.stats?.first_seen)"></span></span>
                <span>Last seen: <span x-text="formatDate(selectedDevice?.stats?.last_seen)"></span></span>
            </div>
        </section>

        <!-- This device's blueprint choices -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-2 shadow-lg">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Blueprint choices (latest)</div>
            <div class="flex flex-wrap gap-1">
                <template x-for="chip in selectedDeviceBlueprintChips()" :key="chip">
                    <span class="text-2xs bg-slate-800 text-slate-300 px-1.5 py-0.5 rounded font-mono" x-text="chip"></span>
                </template>
                <template x-if="selectedDeviceBlueprintChips().length === 0">
                    <span class="text-2xs text-slate-500 italic">No blueprint choices recorded</span>
                </template>
            </div>
        </section>

        <!-- Per-device dwell -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-2 shadow-lg">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Dwell by screen (this device)</div>
            <template x-for="dw in (selectedDevice?.dwell || [])" :key="dw.screen">
                <div class="flex items-center justify-between text-xs gap-2">
                    <span class="text-slate-300 font-mono truncate flex-1" x-text="dw.screen"></span>
                    <div class="flex items-center space-x-2">
                        <div class="bg-slate-800 h-2 rounded-full overflow-hidden w-16">
                            <div class="bg-emerald-500 h-full" :style="'width: ' + Math.min(100, Math.round(((dw.median_ms || 0) / Math.max(...(selectedDevice?.dwell || []).map(d => d.median_ms || 1))) * 100)) + '%'"></div>
                        </div>
                        <span class="text-slate-100 font-semibold w-14 text-right font-mono" x-text="dw.median_formatted || '0s'"></span>
                    </div>
                </div>
            </template>
            <template x-if="!selectedDevice?.dwell || selectedDevice.dwell.length === 0">
                <div class="text-center py-3 text-xs text-slate-500">No screen dwell data for this device.</div>
            </template>
        </section>

        <!-- Session-grouped trail -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 shadow-lg mb-8">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Navigation trail (by session)</div>
            @include('telemetry.partials._session-block')
        </section>
    </div>
</div>
