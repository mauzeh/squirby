/**
 * Chart Component Initialization
 * Automatically initializes all chart components on the page
 */

// Track if Chart.js libraries are loaded
let chartLibrariesLoaded = false;
let chartLibrariesLoading = false;
let chartLoadCallbacks = [];

// Store chart instances for timeframe filtering
const chartInstances = new Map();

/**
 * Calculate linear regression trend line from data points
 */
function calculateTrendLine(dataPoints) {
    if (dataPoints.length < 2) {
        return [];
    }

    // Filter out null values
    const points = dataPoints.filter(point => point.y !== null);
    
    if (points.length < 2) {
        return [];
    }

    // Convert dates to timestamps for calculation
    const timestampPoints = points.map(point => ({
        x: new Date(point.x).getTime(),
        y: point.y
    }));

    const n = timestampPoints.length;
    const sumX = timestampPoints.reduce((sum, p) => sum + p.x, 0);
    const sumY = timestampPoints.reduce((sum, p) => sum + p.y, 0);
    const sumXY = timestampPoints.reduce((sum, p) => sum + (p.x * p.y), 0);
    const sumX2 = timestampPoints.reduce((sum, p) => sum + (p.x * p.x), 0);

    // Avoid division by zero
    const denominator = (n * sumX2 - sumX * sumX);
    if (denominator === 0) {
        return [];
    }

    // Calculate slope (m) and intercept (b) for y = mx + b
    const slope = (n * sumXY - sumX * sumY) / denominator;
    const intercept = (sumY - slope * sumX) / n;

    // Generate trend line points at the start and end
    const firstTimestamp = timestampPoints[0].x;
    const lastTimestamp = timestampPoints[timestampPoints.length - 1].x;

    return [
        {
            x: new Date(firstTimestamp).toISOString(),
            y: Math.round((slope * firstTimestamp + intercept) * 10) / 10
        },
        {
            x: new Date(lastTimestamp).toISOString(),
            y: Math.round((slope * lastTimestamp + intercept) * 10) / 10
        }
    ];
}

/**
 * Filter datasets by timeframe
 */
function filterDatasetsByTimeframe(datasets, timeframe) {
    if (timeframe === 'all') {
        return datasets;
    }

    const now = new Date();
    let cutoffDate;

    switch (timeframe) {
        case '1yr':
            cutoffDate = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
            break;
        case '6mo':
            cutoffDate = new Date(now.getFullYear(), now.getMonth() - 6, now.getDate());
            break;
        case '3mo':
            cutoffDate = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
            break;
        default:
            return datasets;
    }

    return datasets.map(dataset => {
        // Skip trend line datasets - we'll recalculate them
        if (dataset.label === 'Trend') {
            return null;
        }

        // Filter data points by date
        const filteredData = dataset.data.filter(point => {
            const pointDate = new Date(point.x);
            return pointDate >= cutoffDate;
        });

        return {
            ...dataset,
            data: filteredData
        };
    }).filter(dataset => dataset !== null);
}

/**
 * Add trend line to filtered datasets
 */
function addTrendLineToDatasets(datasets) {
    // Find the main data dataset (not a trend line)
    const mainDataset = datasets.find(ds => ds.label !== 'Trend');
    
    if (!mainDataset || mainDataset.data.length < 2) {
        return datasets;
    }

    // Calculate trend line for the filtered data
    const trendLineData = calculateTrendLine(mainDataset.data);
    
    if (trendLineData.length === 0) {
        return datasets;
    }

    // Add trend line dataset
    const trendLineDataset = {
        label: 'Trend',
        data: trendLineData,
        backgroundColor: 'rgba(255, 99, 132, 0.1)',
        borderColor: 'rgba(255, 99, 132, 0.8)',
        borderWidth: 2,
        borderDash: [5, 5],
        pointRadius: 0,
        pointHoverRadius: 0,
        fill: false
    };

    return [...datasets, trendLineDataset];
}

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
        
        // Check if canvas is visible
        if (isElementVisible(canvas)) {
            initializeChart(canvas);
        } else {
            // Set up observer for when it becomes visible
            observeChartVisibility(canvas);
        }
    });
}

/**
 * Initialize a single chart
 */
function initializeChart(canvas) {
    if (canvas.dataset.chartInitialized === 'true') {
        return;
    }
    
    try {
        const type = canvas.dataset.chartType;
        const datasets = JSON.parse(canvas.dataset.chartDatasets || '[]');
        const options = JSON.parse(canvas.dataset.chartOptions || '{}');
        const hasTimeframeSelector = canvas.dataset.chartTimeframeEnabled === 'true';
        
        const ctx = canvas.getContext('2d');
        
        const chart = new Chart(ctx, {
            type: type,
            data: { datasets: datasets },
            options: options
        });
        
        // Store chart instance and original datasets for timeframe filtering
        if (hasTimeframeSelector) {
            chartInstances.set(canvas.id, {
                chart: chart,
                originalDatasets: JSON.parse(JSON.stringify(datasets)) // Deep clone
            });
            
            // Set up timeframe selector buttons
            setupTimeframeSelector(canvas);
            
            // Apply default 6 month filter
            updateChartTimeframe(canvas.id, '6mo');
        }
        
        // Mark as initialized
        canvas.dataset.chartInitialized = 'true';
    } catch (error) {
        console.error('Failed to initialize chart:', error, canvas);
    }
}

/**
 * Set up timeframe selector button handlers
 */
function setupTimeframeSelector(canvas) {
    const container = canvas.closest('.form-container, .chart-container-styled');
    if (!container) return;
    
    const buttons = container.querySelectorAll('.timeframe-btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active state
            buttons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Get timeframe and update chart
            const timeframe = this.dataset.timeframe;
            updateChartTimeframe(canvas.id, timeframe);
        });
    });
}

/**
 * Update chart with new timeframe
 */
function updateChartTimeframe(canvasId, timeframe) {
    const chartData = chartInstances.get(canvasId);
    if (!chartData) return;
    
    const { chart, originalDatasets } = chartData;
    
    // Filter datasets by timeframe
    let filteredDatasets = filterDatasetsByTimeframe(originalDatasets, timeframe);
    
    // Recalculate and add trend line
    filteredDatasets = addTrendLineToDatasets(filteredDatasets);
    
    // Update chart
    chart.data.datasets = filteredDatasets;
    chart.update();
}

/**
 * Check if element is visible (not hidden by display:none, visibility:hidden, or hidden attribute)
 */
function isElementVisible(element) {
    const rect = element.getBoundingClientRect();
    const style = window.getComputedStyle(element);
    
    return rect.width > 0 && 
           rect.height > 0 && 
           style.display !== 'none' && 
           style.visibility !== 'hidden' &&
           !element.hasAttribute('hidden') &&
           !element.closest('[hidden]');
}

/**
 * Set up intersection observer to initialize chart when it becomes visible
 */
function observeChartVisibility(canvas) {
    // Create observer if it doesn't exist
    if (!window.chartVisibilityObserver) {
        window.chartVisibilityObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && isElementVisible(entry.target)) {
                    initializeChart(entry.target);
                    // Stop observing once initialized
                    window.chartVisibilityObserver.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        });
    }
    
    window.chartVisibilityObserver.observe(canvas);
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

// Export functions for use by other components
window.ChartComponent = {
    initializeCharts: initializeCharts,
    loadChartLibraries: loadChartLibraries
};
