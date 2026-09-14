<div id="sessionList" class="space-y-2">
    <!-- Sessions List (newest first, collapsible) -->
    <template x-for="(sess, idx) in deviceSessions" :key="sess.session_id || idx">
        <div class="border border-slate-800 rounded-lg overflow-hidden">
            <button 
                @click="toggleSession(idx)" 
                class="w-full flex items-center justify-between p-2.5 bg-slate-950 hover:bg-slate-900 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-200" x-text="'Session ' + (deviceSessions.length - idx)"></span>
                    <span class="text-2xs text-slate-500" x-text="formatDate(sess.start_time)"></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-2xs bg-slate-800 text-slate-300 px-1.5 py-0.5 rounded-full font-mono" x-text="sess.duration_formatted || '0s'"></span>
                    <span 
                        class="text-slate-500 text-xs transition-transform inline-block duration-200"
                        :style="isSessionOpen(idx) ? 'transform: rotate(0deg)' : 'transform: rotate(-90deg)'">▾</span>
                </div>
            </button>
            <div class="collapsible" :class="{ 'open': isSessionOpen(idx) }">
                <div>
                    <div class="p-2 space-y-1.5">
                        <template x-for="(sc, scIdx) in (sess.events || [])" :key="scIdx">
                            <div class="flex items-center justify-between bg-slate-950 border border-slate-800 rounded-md p-2">
                                <span class="text-xs text-slate-200 font-mono" x-text="sc.event || sc.screen"></span>
                                <span 
                                    class="text-2xs font-mono"
                                    :class="sc.duration_ms ? 'text-slate-400' : 'text-slate-600 italic'"
                                    x-text="sc.duration_formatted || (sc.duration_ms ? sc.duration_ms + 'ms' : '—')"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Load Older Sessions Button -->
    <template x-if="devicePage < deviceLastPage">
        <div class="pt-2 text-center">
            <button 
                @click="loadMoreSessions()" 
                class="px-3 py-1.5 text-xs rounded bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium transition-colors">
                Load older sessions &rarr;
            </button>
        </div>
    </template>

    <!-- Pre-instrumentation / Sessionless Bucket -->
    <template x-if="deviceSessionless && deviceSessionless.length > 0">
        <div class="border border-dashed border-slate-700 rounded-lg overflow-hidden mt-4">
            <button 
                @click="toggleSession('sessionless')" 
                class="w-full flex items-center justify-between p-2.5 bg-slate-950/60 hover:bg-slate-900/60 transition-colors">
                <div class="flex flex-col items-start">
                    <span class="text-xs font-semibold text-slate-400">Before session tracking</span>
                    <span class="text-2xs text-slate-500" x-text="deviceSessionless.length + ' screen views · durations not available'"></span>
                </div>
                <span 
                    class="text-slate-500 text-xs transition-transform inline-block duration-200"
                    :style="isSessionOpen('sessionless') ? 'transform: rotate(0deg)' : 'transform: rotate(-90deg)'">▾</span>
            </button>
            <div class="collapsible" :class="{ 'open': isSessionOpen('sessionless') }">
                <div>
                    <div class="p-2 space-y-1.5">
                        <template x-for="(sc, scIdx) in (sessionlessCapped ? deviceSessionless.slice(0, sessionlessLimit) : deviceSessionless)" :key="scIdx">
                            <div class="flex items-center justify-between bg-slate-950 border border-slate-800 rounded-md p-2">
                                <span class="text-xs text-slate-300 font-mono" x-text="sc.event || sc.screen"></span>
                                <span class="text-2xs font-mono text-slate-500">
                                    <span x-text="formatDate(sc.created_at || sc.ts)"></span> · <span class="text-slate-600">—</span>
                                </span>
                            </div>
                        </template>

                        <template x-if="sessionlessCapped && deviceSessionless.length > sessionlessLimit">
                            <div class="pt-1 text-center">
                                <button 
                                    @click="sessionlessCapped = false" 
                                    class="text-2xs text-blue-400 hover:underline focus:outline-none">
                                    Load more historic events (<span x-text="deviceSessionless.length - sessionlessLimit"></span> remaining)
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
