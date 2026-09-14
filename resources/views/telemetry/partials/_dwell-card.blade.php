<!-- TOTAL CARD 3: DWELL -->
<section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4 shadow-lg">
    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Dwell time</div>
            <div class="text-2xs text-slate-400">Median per screen · since instrumentation</div>
        </div>
    </div>

    <!-- Overall median dwell headline -->
    <div class="bg-slate-950 rounded-lg border border-slate-800 p-3 flex items-baseline justify-between">
        <div>
            <div class="text-2xs text-slate-500 uppercase tracking-wider">Median dwell</div>
            <div class="text-2xl font-extrabold text-white mt-0.5" x-text="dwellData.overall_median_formatted || '0s'">0s</div>
        </div>
        <div class="text-2xs text-slate-500 text-right">across all<br>measured screen visits</div>
    </div>

    <!-- Ranked screens by median dwell -->
    <div class="space-y-2">
        <div class="text-2xs font-semibold uppercase tracking-wider text-slate-500">By screen (median)</div>
        <template x-for="item in visibleDwellScreens" :key="item.screen">
            <div class="flex items-center justify-between text-xs gap-2">
                <span class="text-slate-300 font-mono truncate flex-1" x-text="item.screen"></span>
                <div class="flex items-center space-x-2">
                    <div class="bg-slate-800 h-2 rounded-full overflow-hidden w-16">
                        <div class="bg-emerald-500 h-full" :style="'width: ' + Math.round(((item.median_ms || 0) / maxDwellMedianMs) * 100) + '%'"></div>
                    </div>
                    <span class="text-slate-100 font-semibold w-14 text-right font-mono" x-text="item.median_formatted || '0s'"></span>
                </div>
            </div>
        </template>

        <template x-if="!dwellData.screens || dwellData.screens.length === 0">
            <div class="text-center py-4 text-xs text-slate-500">No screen dwell data in selected range.</div>
        </template>

        <template x-if="dwellData.screens && dwellData.screens.length > 15">
            <div class="pt-1 text-center">
                <button 
                    @click="showAllScreens = !showAllScreens" 
                    class="text-2xs text-blue-400 hover:underline focus:outline-none">
                    <span x-text="showAllScreens ? 'Show top 15' : 'Show all (' + dwellData.screens.length + ' screens)'"></span>
                </button>
            </div>
        </template>
    </div>
</section>
