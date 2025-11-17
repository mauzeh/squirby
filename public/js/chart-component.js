/**
 * Chart Component Initialization
 * Automatically initializes all chart components on the page
 */

// Track if Chart.js libraries are loaded
let chartLibrariesLoaded = false;
let chartLibrariesLoading = false;
let chartLoadCallbacks = [];

/**
 * Load Chart.js libraries dynamically
 */
function loadChartLibraries() {
    return new Promise((resolve, reject) => {
        // If already loaded, resolve immediately
        if (chartLibrariesLoaded) {
            resolve();
            return;
        }
        
        // If currently loading, queue the callback
        if (chartLibrariesLoading) {
            chartLoadCallbacks.push({ resolve, reject });
            return;
        }
        
        chartLibrariesLoading = true;
        
        // Check if Chart.js is already loaded (e.g., from another script)
        if (typeof Chart !== 'undefined') {
            chartLibrariesLoaded = true;
            chartLibrariesLoading = false;
            resolve();
            // Resolve any queued callbacks
            chartLoadCallbacks.forEach(cb => cb.resolve());
            chartLoadCallbacks = [];
            return;
        }
        
        // Load Chart.js
        const chartScript = document.createElement('script');
        chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        chartScript.onload = () => {
            // Load date adapter
            const adapterScript = document.createElement('script');
            adapterScript.src = 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js';
            adapterScript.onload = () => {
                chartLibrariesLoaded = true;
                chartLibrariesLoading = false;
                resolve();
                // Resolve any queued callbacks
                chartLoadCallbacks.forEach(cb => cb.resolve());
                chartLoadCallbacks = [];
            };
            adapterScript.onerror = (error) => {
                chartLibrariesLoading = false;
                reject(error);
                // Reject any queued callbacks
                chartLoadCallbacks.forEach(cb => cb.reject(error));
                chartLoadCallbacks = [];
            };
            document.head.appendChild(adapterScript);
        };
        chartScript.onerror = (error) => {
            chartLibrariesLoading = false;
            reject(error);
            // Reject any queued callbacks
            chartLoadCallbacks.forEach(cb => cb.reject(error));
            chartLoadCallbacks = [];
        };
        document.head.appendChild(chartScript);
    });
}

/**
 * Initialize all charts on the page
 */
function initializeCharts() {
    const canvases = document.querySelectorAll('canvas[data-chart-type]');
    
    if (canvases.length === 0) {
        return;
    }
    
    canvases.forEach(canvas => {
        // Skip if already initialized
        if (canvas.dataset.chartInitialized === 'true') {
            return;
        }
        
        try {
            const type = canvas.dataset.chartType;
            const datasets = JSON.parse(canvas.dataset.chartDatasets || '[]');
            const options = JSON.parse(canvas.dataset.chartOptions || '{}');
            
            const ctx = canvas.getContext('2d');
            
            new Chart(ctx, {
                type: type,
                data: { datasets: datasets },
                options: options
            });
            
            // Mark as initialized
            canvas.dataset.chartInitialized = 'true';
        } catch (error) {
            console.error('Failed to initialize chart:', error, canvas);
        }
    });
}

/**
 * Initialize charts when DOM is ready
 */
function initChartsWhenReady() {
    loadChartLibraries()
        .then(initializeCharts)
        .catch(error => {
            console.error('Failed to load chart libraries:', error);
        });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChartsWhenReady);
} else {
    // DOM is already ready
    initChartsWhenReady();
}
