let telemetryChart = null;

function telemetryDashboard() {
    return {
        view: 'overview',
        since: 'since_launch',
        customDate: '',
        activeChip: 'since_launch',
        metric: 'new',
        page: 1,
        loading: false,
        summaryData: {
            total: 0,
            series: { labels: [], new: [], cumulative: [], active: [], returning: [] },
            bucket: '',
            devices: { data: [], current_page: 1, last_page: 1, total: 0 }
        },
        dwellData: {
            overall_median_ms: 0,
            overall_median_formatted: '0s',
            screens: []
        },
        blueprintData: {
            total: 0,
            distribution: {},
            devices: []
        },
        generatedAt: null,
        showAllScreens: false,

        // Deep dive state
        selectedDevice: null,
        selectedDeviceId: null,
        deviceLoading: false,
        devicePage: 1,
        deviceLastPage: 1,
        deviceSessions: [],
        deviceSessionless: [],
        sessionlessCapped: true,
        sessionlessLimit: 10,
        openSessions: {},
        expandedDeviceIds: [],

        chips: [
            { id: 'since_launch', label: 'Since launch' },
            { id: '7d', label: '7d' },
            { id: '24h', label: '24h' },
            { id: '1h', label: '1h' },
            { id: 'all', label: 'All' },
        ],

        init() {
            this.loadSummary();
            this.loadBlueprint();
        },

        setChip(chipId) {
            this.activeChip = chipId;
            this.since = chipId;
            this.customDate = '';
            this.page = 1;
            this.loadSummary();
            this.loadBlueprint();
        },

        onDateInput() {
            if (this.customDate) {
                this.since = this.customDate;
                this.activeChip = 'custom';
                this.page = 1;
                this.loadSummary();
                this.loadBlueprint();
            }
        },

        loadSummary() {
            this.loading = true;
            const url = window.telemetryRoutes.summary + "?since=" + encodeURIComponent(this.since) + "&page=" + this.page;

            fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                this.summaryData = data;
                if (data.dwell) {
                    this.dwellData = data.dwell;
                }
                if (data.generated_at) {
                    this.generatedAt = data.generated_at;
                }
                this.updateChart();
                this.loading = false;
            })
            .catch(err => {
                console.error('Failed to load summary:', err);
                this.loading = false;
            });
        },

        loadBlueprint() {
            const url = window.telemetryRoutes.blueprint + "?since=" + encodeURIComponent(this.since);

            fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                this.blueprintData = data;
                if (data.generated_at && !this.generatedAt) {
                    this.generatedAt = data.generated_at;
                }
            })
            .catch(err => {
                console.error('Failed to load blueprint:', err);
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
            if (this.metric === 'returning') return 'Returning Devices';
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

        // Deep dive interactions
        openDeepDive(deviceId) {
            this.selectedDeviceId = deviceId;
            this.view = 'device';
            this.devicePage = 1;
            this.deviceSessions = [];
            this.deviceSessionless = [];
            this.openSessions = {};
            this.loadDeviceDetails(deviceId, 1);
            window.scrollTo(0, 0);
        },

        showOverview() {
            this.view = 'overview';
            window.scrollTo(0, 0);
        },

        loadDeviceDetails(deviceId, page = 1) {
            this.deviceLoading = true;
            const deviceParam = (deviceId === null || deviceId === undefined) ? 'null' : deviceId;
            const url = window.telemetryRoutes.device + "?device_id=" + encodeURIComponent(deviceParam) + "&since=" + encodeURIComponent(this.since) + "&page=" + page + "&per_page=10";

            fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                this.selectedDevice = data;
                this.devicePage = data.sessions?.current_page || 1;
                this.deviceLastPage = data.sessions?.last_page || 1;

                const newSessions = data.sessions?.data || [];
                if (page === 1) {
                    this.deviceSessions = newSessions;
                    // Most recent session open by default (index 0)
                    if (this.deviceSessions.length > 0) {
                        this.openSessions[0] = true;
                    }
                } else {
                    this.deviceSessions = [...this.deviceSessions, ...newSessions];
                }

                if (data.sessionless && Array.isArray(data.sessionless.data)) {
                    this.deviceSessionless = data.sessionless.data;
                }
                this.deviceLoading = false;
            })
            .catch(err => {
                console.error('Failed to load device details:', err);
                this.deviceLoading = false;
            });
        },

        loadMoreSessions() {
            if (this.devicePage < this.deviceLastPage) {
                this.loadDeviceDetails(this.selectedDeviceId, this.devicePage + 1);
            }
        },

        toggleSession(idx) {
            this.openSessions[idx] = !this.openSessions[idx];
        },

        isSessionOpen(idx) {
            return !!this.openSessions[idx];
        },

        selectedDeviceBlueprintChips() {
            if (!this.selectedDevice || !this.selectedDevice.blueprint) return [];
            return this.selectionsToChips(this.selectedDevice.blueprint.selections || {});
        },

        selectionsToChips(selections) {
            const chips = [];
            if (!selections || typeof selections !== 'object') return chips;
            for (const [field, val] of Object.entries(selections)) {
                if (val === null || val === undefined) continue;
                if (Array.isArray(val)) {
                    val.forEach(item => { if (item !== null && item !== undefined) chips.push(field + ': ' + item); });
                } else if (typeof val === 'object') {
                    for (const [k, v] of Object.entries(val)) {
                        if (v === true || v === 1 || v === 'true' || v === '1') chips.push(field + ': ' + k);
                        else if (typeof v === 'string' && v !== '') chips.push(field + ': ' + k + '=' + v);
                    }
                } else {
                    chips.push(field + ': ' + val);
                }
            }
            return chips;
        },

        formatDataAge() {
            if (!this.generatedAt) return '';
            try {
                const gen = new Date(this.generatedAt);
                if (isNaN(gen.getTime())) return '';
                const hhmm = gen.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false });
                const diffMs = Date.now() - gen.getTime();
                const diffMins = Math.max(0, Math.floor(diffMs / 60000));
                return `Data as of ${hhmm} · updated ${diffMins}m ago`;
            } catch (e) {
                return '';
            }
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
        },

        get visibleDwellScreens() {
            const screens = this.dwellData.screens || [];
            if (this.showAllScreens) {
                return screens;
            }
            return screens.slice(0, 15);
        },

        get maxDwellMedianMs() {
            const screens = this.dwellData.screens || [];
            if (screens.length === 0) return 1;
            return Math.max(...screens.map(s => s.median_ms || 0), 1);
        }
    };
}
