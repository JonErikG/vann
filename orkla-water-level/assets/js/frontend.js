jQuery(document).ready(function($) {
    console.log('Orkla Frontend JS loaded');
    console.log('AJAX URL:', orkla_ajax.ajax_url);
    console.log('Nonce:', orkla_ajax.nonce);
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
            $.ajax({
                url: orkla_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_water_data',
                    period: selectedPeriod,
                    nonce: orkla_ajax.nonce
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success && response.data) {
                        console.log('Data received:', response.data.length, 'records');
                        updateWaterLevelChart(response.data);
                        updateCurrentStatus(response.data);
                    } else {
                        console.error('Error in response:', response);
                        showError('Error loading data: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    showError('Failed to load vannføring data: ' + error);
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