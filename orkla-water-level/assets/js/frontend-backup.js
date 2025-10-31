jQuery(document).ready(function($) {
    console.log('Orkla Frontend JS loaded');

    if (typeof orkla_ajax === 'undefined') {
        console.error('orkla_ajax is not defined! Scripts may not be enqueued properly.');
        $('.widget-chart-container').html('<div class="orkla-error" style="padding: 40px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; color: #856404;"><h3>⚠ Configuration Error</h3><p>Plugin scripts are not properly configured. Please refresh the page or contact site administrator.</p></div>');
        return;
    }

    console.log('AJAX URL:', orkla_ajax.ajax_url);
    console.log('Nonce:', orkla_ajax.nonce);

    if (typeof Chart === 'undefined') {
        console.error('Chart.js is NOT loaded! Cannot render graphs.');
        console.error('This may be due to:');
        console.error('1. CDN blocked by firewall or ad blocker');
        console.error('2. Network connectivity issue');
        console.error('3. Script loading order problem');
        $('.widget-chart-container').html('<div class="orkla-error" style="padding: 40px; text-align: center; background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; color: #721c24;"><h3>⚠ Chart.js Not Loaded</h3><p>The Chart.js library failed to load.</p><p style="margin-top: 15px; font-size: 14px;"><strong>Possible causes:</strong></p><ul style="text-align: left; max-width: 400px; margin: 15px auto; list-style: disc; padding-left: 20px;"><li>Ad blocker blocking Chart.js</li><li>Firewall blocking CDN access</li><li>Network connectivity issue</li></ul><p style="margin-top: 15px;"><strong>Solution:</strong> Try disabling ad blockers, clear browser cache (Ctrl+Shift+R), or contact site administrator.</p></div>');
        return;
    } else {
        console.log('✓ Chart.js loaded successfully (version ' + Chart.version + ')');
    }

    let widgetChart = null;
    // Initialize water level widget
    if ($('#widget-chart').length) {
        console.log('Initializing water level widget');
        initializeWaterLevelWidget();
    }
    function initializeWaterLevelWidget() {
        const period = $('.orkla-water-widget').data('period') || 'today';
        // Load initial data
        loadWaterLevelData(period);
        // Event listeners
        $('#widget-period').on('change', function() {
            loadWaterLevelData($(this).val());
        });
        $('#widget-refresh').on('click', function() {
            const currentPeriod = $('#widget-period').val() || period;
            loadWaterLevelData(currentPeriod);
        });
        function loadWaterLevelData(selectedPeriod) {
            console.log('Loading water level data for period:', selectedPeriod);
            $('.current-level').text('Loading...');

            console.log('DEBUG: AJAX URL:', orkla_ajax.ajax_url);
            console.log('DEBUG: Nonce:', orkla_ajax.nonce);

            $.ajax({
                url: orkla_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_water_data',
                    period: selectedPeriod,
                    nonce: orkla_ajax.nonce
                },
                success: function(response) {
                    console.log('✓ AJAX response received:', response);
                    if (response.success && response.data) {
                        console.log('✓ Data received:', response.data.length, 'records');
                        if (response.data.length === 0) {
                            showError('<strong>No data available for this period.</strong><br><br>This could mean:<ul style="text-align: left; max-width: 400px; margin: 15px auto;"><li>No data has been imported yet</li><li>The selected time period has no recorded data</li></ul><p>Please check the <a href="' + orkla_ajax.ajax_url.replace('admin-ajax.php', 'admin.php?page=orkla-water-level-debug') + '" style="color: #0073aa; text-decoration: underline;">Debug Status page</a> in the admin panel.</p>');
                        } else {
                            console.log('✓ First record:', response.data[0]);
                            console.log('✓ Last record:', response.data[response.data.length - 1]);
                            updateWaterLevelChart(response.data);
                            updateCurrentStatus(response.data);
                        }
                    } else {
                        console.error('✗ Error in response:', response);
                        var errorMsg = response.data || 'Unknown error';
                        showError('<strong>Error loading data:</strong> ' + errorMsg + '<br><br><p style="font-size: 14px;">Check browser console (F12) for details or visit the <a href="' + orkla_ajax.ajax_url.replace('admin-ajax.php', 'admin.php?page=orkla-water-level-debug') + '" style="color: #0073aa; text-decoration: underline;">Debug Status page</a> in admin.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('✗ AJAX request failed:', status, error);
                    console.error('✗ Response text:', xhr.responseText);
                    console.error('✗ Status code:', xhr.status);
                    console.error('✗ Full XHR object:', xhr);

                    var errorDetail = '';
                    if (xhr.status === 0) {
                        errorDetail = 'Network error - Cannot connect to server. Check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorDetail = 'AJAX endpoint not found (404). Plugin may not be activated correctly.';
                    } else if (xhr.status === 500) {
                        errorDetail = 'Server error (500). Check WordPress error logs.';
                    } else if (xhr.status === 403) {
                        errorDetail = 'Access forbidden (403). Nonce verification may have failed.';
                    } else {
                        errorDetail = 'Error: ' + error + ' (Status: ' + xhr.status + ')';
                    }

                    showError('<strong>Failed to load vannføring data</strong><br><br>' + errorDetail + '<br><br><p style="font-size: 14px;">Check browser console (F12) for details or visit the <a href="' + orkla_ajax.ajax_url.replace('admin-ajax.php', 'admin.php?page=orkla-water-level-debug') + '" style="color: #0073aa; text-decoration: underline;">Debug Status page</a> in admin.</p>');
                }
            });
        }
        function updateWaterLevelChart(data) {
            console.log('Updating chart with data:', data);
            if (!data || data.length === 0) {
                console.log('No data to display');
                showError('No data available');
                return;
            }
            const datasets = [
                {
                    label: 'Vannføring Oppstrøms Brattset',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.vannforing_brattset || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'red',
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Vannføring Syrstad',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.vannforing_syrstad || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 0, 255, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Vannføring Storsteinshølen',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.vannforing_storsteinsholen || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'purple',
                    backgroundColor: 'rgba(128, 0, 128, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Produksjonsvannføring Brattset',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.produksjon_brattset || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'green',
                    backgroundColor: 'rgba(0, 128, 0, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Produksjonsvannføring Grana',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.produksjon_grana || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'orange',
                    backgroundColor: 'rgba(255, 165, 0, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Produksjon Svorkmo',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.produksjon_svorkmo || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'brown',
                    backgroundColor: 'rgba(165, 42, 42, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Rennebu oppstrøms grana',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.rennebu_oppstroms || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'gray',
                    backgroundColor: 'rgba(128, 128, 128, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Nedstrøms Svorkmo kraftverk',
                    data: data.map(item => ({ x: item.timestamp, y: parseFloat(item.nedstroms_svorkmo || 0) })).filter(item => !isNaN(item.y) && item.y !== null),
                    borderColor: 'pink',
                    backgroundColor: 'rgba(255, 192, 203, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                }
            ];
            const activeDatasets = datasets.filter(dataset => dataset.data.length > 0);
            console.log('Active datasets:', activeDatasets.length);
            if (widgetChart) {
                widgetChart.destroy();
            }
            const ctx = document.getElementById('widget-chart').getContext('2d');
            widgetChart = new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: activeDatasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                displayFormats: {
                                    hour: 'HH:00',
                                    day: 'MMM dd',
                                    week: 'MMM dd',
                                    month: 'MMM yyyy'
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Vannføring (m³/sek)'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.3)',
                            borderWidth: 1,
                            callbacks: {
                                title: function(context) {
                                    return new Date(context[0].parsed.x).toLocaleString();
                                },
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' m³/sek';
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });
        }
        function updateCurrentStatus(data) {
            // Current status display removed - just the chart now
        }
        function showError(message) {
            console.error('Showing error:', message);
            $('.widget-chart-container').html('<div class="orkla-error">' + message + '</div>');
        }
    }
});