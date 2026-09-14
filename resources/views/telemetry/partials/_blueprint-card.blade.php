<!-- TOTAL CARD 2: BLUEPRINT (all fields) -->
<section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4 shadow-lg">
    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Blueprint choices</div>
            <div class="text-2xs text-slate-400">Latest per device · all fields</div>
        </div>
        <span class="text-2xs bg-slate-800 text-slate-300 font-semibold px-2 py-0.5 rounded-full" x-text="(blueprintData.total || 0) + ' devices'">0 devices</span>
    </div>

    <!-- One block per field; bars = share of devices -->
    <div class="space-y-3">
        <template x-for="(values, field) in blueprintData.distribution" :key="field">
            <div class="space-y-1.5">
                <div class="text-xs font-semibold text-slate-300 capitalize" x-text="field.replace(/_/g, ' ')"></div>
                <template x-for="(count, val) in values" :key="val">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-mono" x-text="val"></span>
                        <div class="flex items-center space-x-2">
                            <div class="bg-slate-800 h-2 rounded-full overflow-hidden w-24">
                                <div class="bg-blue-500 h-full" :style="'width: ' + (blueprintData.total > 0 ? Math.round((count / blueprintData.total) * 100) : 0) + '%'"></div>
                            </div>
                            <span class="text-slate-200 font-semibold w-8 text-right font-mono" x-text="count"></span>
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <template x-if="!blueprintData.distribution || Object.keys(blueprintData.distribution).length === 0">
            <div class="text-center py-4 text-xs text-slate-500">No blueprint choices in selected range.</div>
        </template>
    </div>
</section>
