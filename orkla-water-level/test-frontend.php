<?php
/**
 * Orkla Water Level - Frontend Test
 * Upload this file to your WordPress root and access it via browser
 * Example: https://yoursite.com/test-frontend.php
 */

// Load WordPress
require_once('wp-load.php');

echo '<!DOCTYPE html><html><head><title>Orkla Frontend Test</title>';
echo '<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; }
h2 { color: #666; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
.success { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
.info { color: #007cba; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 400px; }
#chart-container { height: 400px; margin: 20px 0; }
button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
button:hover { background: #005a87; }
</style>';
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>';
echo '</head><body>';

echo '<h1>🔬 Orkla Water Level - Frontend Test</h1>';

// Test 1: Check if scripts are loaded
echo '<div class="section">';
echo '<h2>Test 1: Chart.js Library</h2>';
echo '<p id="chartjs-status">Checking...</p>';
echo '<script>
if (typeof Chart !== "undefined") {
    document.getElementById("chartjs-status").innerHTML = "<span class=\"success\">✓ Chart.js loaded successfully (version " + Chart.version + ")</span>";
} else {
    document.getElementById("chartjs-status").innerHTML = "<span class=\"error\">✗ Chart.js not loaded!</span>";
}
</script>';
echo '</div>';

// Test 2: AJAX Endpoint
echo '<div class="section">';
echo '<h2>Test 2: AJAX Data Fetch</h2>';
echo '<button onclick="testAjax()">Test AJAX Request</button>';
echo '<div id="ajax-result"></div>';
echo '</div>';

// Test 3: Render Chart
echo '<div class="section">';
echo '<h2>Test 3: Chart Rendering</h2>';
echo '<button onclick="renderChart()">Render Test Chart</button>';
echo '<div id="chart-container"><canvas id="test-chart"></canvas></div>';
echo '<div id="chart-status"></div>';
echo '</div>';

// Test 4: Console Log
echo '<div class="section">';
echo '<h2>Test 4: Browser Console</h2>';
echo '<p>Open browser console (F12) and check for any errors or warnings.</p>';
echo '<button onclick="console.log(\'Test log from Orkla Frontend\')">Send Test Log</button>';
echo '</div>';

echo '<script>
var ajaxUrl = "' . admin_url('admin-ajax.php') . '";
var nonce = "' . wp_create_nonce('orkla_nonce') . '";
var chartInstance = null;

console.log("Orkla Frontend Test - Initialized");
console.log("AJAX URL:", ajaxUrl);
console.log("Nonce:", nonce);

function testAjax() {
    var resultDiv = document.getElementById("ajax-result");
    resultDiv.innerHTML = "<p class=\"info\">Loading...</p>";

    console.log("Testing AJAX request to:", ajaxUrl);

    var xhr = new XMLHttpRequest();
    xhr.open("POST", ajaxUrl, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        console.log("AJAX Response received:", xhr.status);
        console.log("Response text:", xhr.responseText);

        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                console.log("Parsed response:", response);

                if (response.success && response.data) {
                    var html = "<p class=\"success\">✓ AJAX request successful!</p>";
                    html += "<p><strong>Records returned:</strong> " + response.data.length + "</p>";
                    html += "<p><strong>Sample data:</strong></p>";
                    html += "<pre>" + JSON.stringify(response.data.slice(0, 3), null, 2) + "</pre>";
                    resultDiv.innerHTML = html;

                    // Store data for chart test
                    window.orklaTestData = response.data;
                } else {
                    resultDiv.innerHTML = "<p class=\"error\">✗ No data returned</p><pre>" + JSON.stringify(response, null, 2) + "</pre>";
                }
            } catch(e) {
                console.error("JSON parse error:", e);
                resultDiv.innerHTML = "<p class=\"error\">✗ JSON parse error: " + e.message + "</p><pre>" + xhr.responseText + "</pre>";
            }
        } else {
            resultDiv.innerHTML = "<p class=\"error\">✗ HTTP error: " + xhr.status + "</p><pre>" + xhr.responseText + "</pre>";
        }
    };

    xhr.onerror = function() {
        console.error("Network error");
        resultDiv.innerHTML = "<p class=\"error\">✗ Network error</p>";
    };

    xhr.send("action=get_water_data&period=today&nonce=" + nonce);
}

function renderChart() {
    var statusDiv = document.getElementById("chart-status");

    if (!window.orklaTestData) {
        statusDiv.innerHTML = "<p class=\"error\">⚠ No data available. Run AJAX test first.</p>";
        return;
    }

    console.log("Rendering chart with data:", window.orklaTestData);

    var ctx = document.getElementById("test-chart");

    if (chartInstance) {
        chartInstance.destroy();
    }

    try {
        chartInstance = new Chart(ctx, {
            type: "line",
            data: {
                datasets: [{
                    label: "Vannføring Brattset",
                    data: window.orklaTestData.map(function(item) {
                        return {
                            x: item.timestamp,
                            y: parseFloat(item.vannforing_brattset) || 0
                        };
                    }),
                    borderColor: "rgb(59, 130, 246)",
                    backgroundColor: "rgba(59, 130, 246, 0.1)",
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: "time",
                        time: {
                            unit: "hour"
                        }
                    },
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });

        statusDiv.innerHTML = "<p class=\"success\">✓ Chart rendered successfully!</p>";
        console.log("Chart instance created:", chartInstance);
    } catch(e) {
        console.error("Chart render error:", e);
        statusDiv.innerHTML = "<p class=\"error\">✗ Chart error: " + e.message + "</p>";
    }
}

// Auto-run AJAX test on load
setTimeout(function() {
    testAjax();
}, 500);
</script>';

echo '<div class="section">';
echo '<h2>Instructions</h2>';
echo '<ol>';
echo '<li>Wait for the AJAX test to complete automatically</li>';
echo '<li>Check if Chart.js loaded (Test 1)</li>';
echo '<li>Verify AJAX returns data (Test 2)</li>';
echo '<li>Click "Render Test Chart" to test chart rendering (Test 3)</li>';
echo '<li>Open browser console (F12) to check for errors</li>';
echo '<li><strong>Delete this file after testing</strong></li>';
echo '</ol>';
echo '</div>';

echo '</body></html>';
