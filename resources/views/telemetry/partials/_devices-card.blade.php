<!-- TOTAL CARD 1: DEVICES -->
<section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4 shadow-lg">
    <div class="flex items-baseline justify-between">
        <div>
            <div class="text-2xs font-medium text-slate-400 uppercase tracking-wider">Distinct devices</div>
            <div class="text-3xl font-extrabold text-white mt-0.5" x-text="summaryData.total || 0">0</div>
        </div>
        <div class="text-right">
            <div class="text-2xs text-slate-500 uppercase tracking-wider">New today</div>
            <div class="text-lg font-bold text-emerald-400" x-text="summaryData.series && summaryData.series.new ? '+' + (summaryData.series.new[summaryData.series.new.length - 1] || 0) : '+0'">+0</div>
        </div>
    </div>

    <!-- 3-way metric toggle -->
    <div class="flex bg-slate-950 p-1 rounded-lg border border-slate-800 text-xs">
        <button 
            @click="toggleMetric('new')" 
            :class="metric === 'new' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
            class="metric-btn flex-1 py-1 rounded-md text-center transition-all">
            New
        </button>
        <button 
            @click="toggleMetric('cumulative')" 
            :class="metric === 'cumulative' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
            class="metric-btn flex-1 py-1 rounded-md text-center transition-all">
            Cumulative
        </button>
        <button 
            @click="toggleMetric('active')" 
            :class="metric === 'active' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
            class="metric-btn flex-1 py-1 rounded-md text-center transition-all">
            Active
        </button>
    </div>

    <!-- Chart Container -->
    <div class="relative h-48 w-full">
        <canvas id="telemetryChart"></canvas>
    </div>
    <div class="text-2xs text-slate-500 text-center">
        <span x-text="summaryData.bucket ? summaryData.bucket + ' buckets' : 'Daily buckets'">Daily buckets</span> · <span x-text="getMetricLabel()">New devices</span>
    </div>
</section>
