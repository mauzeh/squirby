<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Telemetry Admin — Squirby</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen antialiased">
    <div x-data="telemetryDashboard()" x-init="init()" class="max-w-md mx-auto min-h-screen flex flex-col p-4 space-y-6">

        <!-- Top Header -->
        <header class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div>
                <h1 class="text-xl font-bold text-slate-100 tracking-tight">Telemetry Admin</h1>
                <p class="text-xs text-slate-400">Anonymous Screen-View Analytics</p>
            </div>
            <div x-show="loading" class="flex items-center space-x-2 text-xs text-blue-400">
                <svg class="animate-spin h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Syncing</span>
            </div>
        </header>

        <!-- Date Filter Controls -->
        <section class="space-y-2">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date Filter</div>
            
            <!-- Preset Chips -->
            <div class="flex flex-wrap gap-1.5">
                <template x-for="chip in chips" :key="chip.id">
                    <button 
                        @click="setChip(chip.id)" 
                        :class="activeChip === chip.id ? 'bg-blue-600 text-white font-medium shadow' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                        class="px-2.5 py-1 text-xs rounded-full transition-colors">
                        <span x-text="chip.label"></span>
                    </button>
                </template>
            </div>

            <!-- Custom Date Floor -->
            <div class="flex items-center space-x-2 pt-1">
                <label for="custom-date" class="text-xs text-slate-400 whitespace-nowrap">Custom floor:</label>
                <input 
                    id="custom-date"
                    type="date" 
                    x-model="customDate" 
                    @change="onDateInput()" 
                    class="bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 focus:outline-none w-full">
            </div>
        </section>

        <!-- Section A: Headline & Chart -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4 shadow-lg">
            <!-- Headline Count -->
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Distinct Devices</div>
                    <div class="text-3xl font-extrabold text-white mt-0.5" x-text="summaryData.total">0</div>
                </div>
                <div class="text-right">
                    <span class="text-2xs uppercase tracking-wider bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full" x-text="summaryData.bucket ? summaryData.bucket + ' buckets' : ''"></span>
                </div>
            </div>

            <!-- 3-Way Metric Toggle -->
            <div class="flex bg-slate-950 p-1 rounded-lg border border-slate-800 text-xs">
                <button 
                    @click="toggleMetric('new')" 
                    :class="metric === 'new' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1 text-center rounded-md transition-all">
                    New
                </button>
                <button 
                    @click="toggleMetric('cumulative')" 
                    :class="metric === 'cumulative' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1 text-center rounded-md transition-all">
                    Cumulative
                </button>
                <button 
                    @click="toggleMetric('active')" 
                    :class="metric === 'active' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1 text-center rounded-md transition-all">
                    Active
                </button>
            </div>

            <!-- Chart Container -->
            <div class="relative h-48 w-full">
                <canvas id="telemetryChart"></canvas>
            </div>
        </section>

        <!-- Tab Bar: Devices / Trail / Blueprint -->
        <div class="flex bg-slate-950 p-1 rounded-lg border border-slate-800 text-xs">
            <button
                @click="activeTab = 'devices'"
                :class="activeTab === 'devices' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                class="flex-1 py-1.5 text-center rounded-md transition-all">
                Devices
            </button>
            <button
                @click="activeTab = 'trail'"
                :class="activeTab === 'trail' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                class="flex-1 py-1.5 text-center rounded-md transition-all">
                Trail
            </button>
            <button
                @click="activeTab = 'blueprint'; loadBlueprintOnce()"
                :class="activeTab === 'blueprint' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-slate-400 hover:text-slate-200'"
                class="flex-1 py-1.5 text-center rounded-md transition-all">
                Blueprint
            </button>
        </div>

        <!-- Section B: Paginated Device List -->
        <section x-show="activeTab === 'devices'" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 shadow-lg">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Device List</h2>
                <div class="text-xs text-slate-400">
                    Page <span x-text="summaryData.devices.current_page">1</span> of <span x-text="summaryData.devices.last_page">1</span>
                </div>
            </div>

            <!-- List of Devices -->
            <div class="space-y-2">
                <template x-if="summaryData.devices.data.length === 0">
                    <div class="text-center py-6 text-xs text-slate-500">No active devices in selected range.</div>
                </template>

                <template x-for="device in summaryData.devices.data" :key="device.device_id ?? 'null_device'">
                    <div 
                        @click="selectDevice(device.device_id)"
                        :class="selectedDeviceId === device.device_id ? 'border-blue-500 bg-slate-850 ring-1 ring-blue-500' : 'border-slate-800 hover:border-slate-700 bg-slate-950'"
                        class="p-2.5 rounded-lg border transition-all cursor-pointer flex flex-col space-y-1">
                        
                        <div class="flex items-center justify-between">
                            <!-- Device ID with Shortened / Expanded display -->
                            <div class="flex items-center space-x-1.5 overflow-hidden">
                                <template x-if="device.device_id === null">
                                    <span class="text-xs font-mono font-semibold text-amber-400 bg-amber-950/50 border border-amber-800/50 px-1.5 py-0.5 rounded">no value</span>
                                </template>
                                <template x-if="device.device_id !== null">
                                    <div class="flex items-center space-x-1 font-mono text-xs text-slate-200 truncate">
                                        <span x-text="isDeviceExpanded(device.device_id) ? device.device_id : (device.device_id.substring(0, 8) + '...')"></span>
                                        <button 
                                            @click.stop="toggleDeviceExpand(device.device_id)" 
                                            class="text-2xs text-slate-400 hover:text-blue-400 underline ml-1">
                                            <span x-text="isDeviceExpanded(device.device_id) ? '[less]' : '[full]'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            
                            <span class="text-2xs bg-slate-800 text-slate-300 font-semibold px-2 py-0.5 rounded-full" x-text="device.event_rows + ' events'"></span>
                        </div>

                        <div class="flex items-center justify-between text-2xs text-slate-400 pt-0.5">
                            <span>Last active: <span x-text="formatDate(device.last_seen)"></span></span>
                            <span class="text-blue-400 hover:underline">View trail &rarr;</span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                <button 
                    @click="changePage(summaryData.devices.current_page - 1)" 
                    :disabled="summaryData.devices.current_page <= 1"
                    :class="summaryData.devices.current_page <= 1 ? 'opacity-40 cursor-not-allowed bg-slate-800 text-slate-500' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'"
                    class="px-3 py-1 text-xs rounded transition-colors">
                    &larr; Prev
                </button>
                <span class="text-xs text-slate-400" x-text="summaryData.devices.total + ' total devices'"></span>
                <button 
                    @click="changePage(summaryData.devices.current_page + 1)" 
                    :disabled="summaryData.devices.current_page >= summaryData.devices.last_page"
                    :class="summaryData.devices.current_page >= summaryData.devices.last_page ? 'opacity-40 cursor-not-allowed bg-slate-800 text-slate-500' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'"
                    class="px-3 py-1 text-xs rounded transition-colors">
                    Next &rarr;
                </button>
            </div>
        </section>

        <!-- Section C: Selected Device Navigation Trail -->
        <section x-show="activeTab === 'trail'" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 shadow-lg mb-8">
            <div class="border-b border-slate-800 pb-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Device Navigation Trail</h2>
                    <button
                        @click="activeTab = 'devices'"
                        class="text-2xs text-blue-400 hover:underline whitespace-nowrap">
                        &larr; Devices
                    </button>
                </div>
                <p class="text-xs font-mono text-slate-400 truncate mt-0.5">
                    <span x-text="selectedDeviceId === undefined ? 'Select a device' : (selectedDeviceId === null ? 'no value' : selectedDeviceId)"></span>
                </p>
            </div>

            <div x-show="trailLoading" class="py-6 text-center text-xs text-blue-400 space-x-2">
                <svg class="animate-spin inline h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading trail events...</span>
            </div>

            <div x-show="!trailLoading">
                <!-- No device selected yet -->
                <div x-show="selectedDeviceId === undefined" class="text-center py-6 text-xs text-slate-500">
                    Tap "View trail" on any device to inspect its screen navigation history.
                </div>

                <!-- Device selected but no events -->
                <div x-show="selectedDeviceId !== undefined && trailEvents.length === 0" class="text-center py-6 text-xs text-slate-500">
                    No navigation events recorded for this device.
                </div>

                <!-- Device selected with events -->
                <div x-show="selectedDeviceId !== undefined && trailEvents.length > 0" class="relative pl-4 border-l-2 border-slate-800 space-y-3 my-2">
                    <template x-for="(evt, idx) in trailEvents" :key="idx">
                        <div class="relative group">
                            <!-- Timeline dot -->
                            <div class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-blue-500 border-2 border-slate-900"></div>

                            <div class="bg-slate-950 p-2 rounded-md border border-slate-800 flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-semibold text-slate-200" x-text="evt.screen"></div>
                                    <div class="text-2xs text-slate-400 font-mono" x-text="formatDate(evt.ts)"></div>
                                </div>
                                <span class="text-2xs font-mono text-slate-500" x-text="'#' + (idx + 1)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- Section D: Blueprint Choices Distribution -->
        <section x-show="activeTab === 'blueprint'" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4 shadow-lg mb-8">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Blueprint Choices</h2>
                    <p class="text-2xs text-slate-400">Onboarding snapshot selections (latest per device)</p>
                </div>
                <span class="text-2xs bg-slate-800 text-slate-300 font-semibold px-2 py-0.5 rounded-full" x-text="blueprintData.total + ' devices'"></span>
            </div>

            <div x-show="blueprintLoading" class="py-6 text-center text-xs text-blue-400 space-x-2">
                <svg class="animate-spin inline h-4 w-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading blueprint choices...</span>
            </div>

            <div x-show="!blueprintLoading">
                <template x-if="blueprintData.total === 0">
                    <div class="text-center py-6 text-xs text-slate-500">No blueprint snapshots in selected range.</div>
                </template>

                <div x-show="blueprintData.total > 0" class="space-y-4">
                    <!-- Distribution Cards -->
                    <template x-for="(values, field) in blueprintData.distribution" :key="field">
                        <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 space-y-2">
                            <div class="text-xs font-semibold text-slate-300 capitalize" x-text="field.replace(/_/g, ' ')"></div>
                            
                            <div class="space-y-1.5">
                                <template x-for="(count, val) in values" :key="val">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-slate-400 font-mono" x-text="val"></span>
                                        <div class="flex items-center space-x-2">
                                            <div class="bg-slate-800 h-2 rounded-full overflow-hidden w-24">
                                                <div class="bg-blue-500 h-full rounded-full" :style="'width: ' + (blueprintData.total > 0 ? Math.round((count / blueprintData.total) * 100) : 0) + '%'"></div>
                                            </div>
                                            <span class="text-slate-200 font-semibold w-8 text-right font-mono" x-text="count"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>

    <script>
        let telemetryChart = null;

        function telemetryDashboard() {
            return {
                since: 'since_launch',
                customDate: '',
                activeChip: 'since_launch',
                metric: 'new',
                page: 1,
                activeTab: 'devices',
                loading: false,
                trailLoading: false,
                blueprintLoading: false,
                blueprintLoaded: false,
                blueprintData: {
                    total: 0,
                    distribution: {},
                    devices: []
                },
                summaryData: {
                    total: 0,
                    series: { labels: [], new: [], cumulative: [], active: [] },
                    bucket: '',
                    devices: { data: [], current_page: 1, last_page: 1, total: 0 }
                },
                selectedDeviceId: undefined,
                expandedDeviceIds: [],
                trailEvents: [],

                chips: [
                    { id: 'since_launch', label: 'Since launch' },
                    { id: '30d', label: '30d' },
                    { id: '7d', label: '7d' },
                    { id: '24h', label: '24h' },
                    { id: '12h', label: '12h' },
                    { id: '6h', label: '6h' },
                    { id: '3h', label: '3h' },
                    { id: '1h', label: '1h' },
                    { id: 'all', label: 'All' },
                ],

                init() {
                    this.loadSummary();
                },

                setChip(chipId) {
                    this.activeChip = chipId;
                    this.since = chipId;
                    this.customDate = '';
                    this.page = 1;
                    this.blueprintLoaded = false;
                    this.loadSummary();
                    if (this.activeTab === 'blueprint') {
                        this.loadBlueprint();
                    }
                },

                onDateInput() {
                    if (this.customDate) {
                        this.since = this.customDate;
                        this.activeChip = 'custom';
                        this.page = 1;
                        this.blueprintLoaded = false;
                        this.loadSummary();
                        if (this.activeTab === 'blueprint') {
                            this.loadBlueprint();
                        }
                    }
                },

                loadSummary() {
                    this.loading = true;
                    const url = "{{ route('telemetry.summary') }}?since=" + encodeURIComponent(this.since) + "&page=" + this.page;
                    
                    fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.summaryData = data;
                        this.updateChart();
                        this.loading = false;
                    })
                    .catch(err => {
                        console.error('Failed to load summary:', err);
                        this.loading = false;
                    });
                },

                toggleMetric(m) {
                    this.metric = m;
                    this.updateChart();
                },

                updateChart() {
                    const labels = (this.summaryData.series && this.summaryData.series.labels) || [];
                    const seriesData = this.getMetricData();
                    const labelName = this.getMetricLabel();

                    if (!telemetryChart) {
                        const ctx = document.getElementById('telemetryChart');
                        if (!ctx) return;
                        
                        telemetryChart = new Chart(ctx.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: labelName,
                                    data: seriesData,
                                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                    borderColor: 'rgb(59, 130, 246)',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { 
                                            precision: 0,
                                            color: '#94a3b8' 
                                        },
                                        grid: { 
                                            color: 'rgba(51, 65, 85, 0.5)' 
                                        }
                                    },
                                    x: {
                                        ticks: { color: '#94a3b8' },
                                        grid: { display: false }
                                    }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    } else {
                        telemetryChart.data.labels = labels;
                        telemetryChart.data.datasets[0].label = labelName;
                        telemetryChart.data.datasets[0].data = seriesData;
                        telemetryChart.update();
                    }
                },

                getMetricData() {
                    return (this.summaryData.series && this.summaryData.series[this.metric]) || [];
                },

                getMetricLabel() {
                    if (this.metric === 'new') return 'New Devices';
                    if (this.metric === 'cumulative') return 'Cumulative Devices';
                    if (this.metric === 'active') return 'Active Devices';
                    return 'Devices';
                },

                changePage(newPage) {
                    if (newPage >= 1 && newPage <= this.summaryData.devices.last_page) {
                        this.page = newPage;
                        this.loadSummary();
                    }
                },

                toggleDeviceExpand(deviceId) {
                    if (!deviceId) return;
                    if (this.expandedDeviceIds.includes(deviceId)) {
                        this.expandedDeviceIds = this.expandedDeviceIds.filter(id => id !== deviceId);
                    } else {
                        this.expandedDeviceIds.push(deviceId);
                    }
                },

                isDeviceExpanded(deviceId) {
                    return deviceId && this.expandedDeviceIds.includes(deviceId);
                },

                selectDevice(deviceId) {
                    this.selectedDeviceId = deviceId;
                    this.activeTab = 'trail';
                    this.loadTrail(deviceId);
                },

                loadTrail(deviceId) {
                    this.trailLoading = true;
                    const deviceParam = (deviceId === null || deviceId === undefined) ? 'null' : deviceId;
                    const url = "{{ route('telemetry.trail') }}?device_id=" + encodeURIComponent(deviceParam) + "&since=" + encodeURIComponent(this.since);

                    fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.trailEvents = data;
                        this.trailLoading = false;
                    })
                    .catch(err => {
                        console.error('Failed to load trail:', err);
                        this.trailLoading = false;
                    });
                },

                loadBlueprintOnce() {
                    if (!this.blueprintLoaded) {
                        this.loadBlueprint();
                    }
                },

                loadBlueprint() {
                    this.blueprintLoading = true;
                    const url = "{{ route('telemetry.blueprint') }}?since=" + encodeURIComponent(this.since);

                    fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.blueprintData = data;
                        this.blueprintLoaded = true;
                        this.blueprintLoading = false;
                    })
                    .catch(err => {
                        console.error('Failed to load blueprint:', err);
                        this.blueprintLoading = false;
                    });
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    try {
                        const d = new Date(dateStr);
                        if (isNaN(d.getTime())) return dateStr;
                        return d.toLocaleString(undefined, { 
                            month: 'short', 
                            day: 'numeric', 
                            hour: '2-digit', 
                            minute: '2-digit'
                        });
                    } catch (e) {
                        return dateStr;
                    }
                }
            };
        }
    </script>
</body>
</html>
