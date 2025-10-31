jQuery(document).ready(function($) {
    console.log('Orkla Water Level - Frontend initialized');

    if (typeof orkla_ajax === 'undefined') {
        console.error('Configuration error: orkla_ajax not defined');
        showError('Configuration error. Please refresh the page.');
        return;
    }

    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded');
        showError('Chart library failed to load. Try disabling ad blockers or clearing cache (Ctrl+Shift+R).');
        return;
    }

    console.log('Chart.js version:', Chart.version);

    let waterLevelChart = null;

    if ($('#widget-chart').length) {
        initializeWidget();
    }

    function initializeWidget() {
        const period = $('.orkla-water-widget').data('period') || 'today';
        loadData(period);

        $('#widget-period').on('change', function() {
            loadData($(this).val());
        });

        $('#widget-refresh').on('click', function() {
            loadData($('#widget-period').val() || period);
        });
    }

    function loadData(period) {
        console.log('Loading data for period:', period);
        $('.widget-chart-container').html('<div class="orkla-loading">Loading data...</div>');

        $.ajax({
            url: orkla_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'get_water_data',
                period: period,
                nonce: orkla_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    console.log('Data loaded:', response.data.length, 'records');
                    $('.widget-chart-container').html('<canvas id="widget-chart"></canvas>');
                    renderChart(response.data);
                } else {
                    showError('No data available for this period');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.status);
                let msg = 'Failed to load data';
                if (xhr.status === 0) msg += ' - Network error';
                else if (xhr.status === 404) msg += ' - Endpoint not found';
                else if (xhr.status === 500) msg += ' - Server error';
                showError(msg);
            }
        });
    }

    function renderChart(data) {
        const datasets = [
            { key: 'vannforing_brattset', label: 'Vannføring Oppstrøms Brattset', color: '#ef4444' },
            { key: 'vannforing_syrstad', label: 'Vannføring Syrstad', color: '#3b82f6' },
            { key: 'vannforing_storsteinsholen', label: 'Vannføring Storsteinshølen', color: '#8b5cf6' },
            { key: 'produksjon_brattset', label: 'Produksjonsvannføring Brattset', color: '#10b981' },
            { key: 'produksjon_grana', label: 'Produksjonsvannføring Grana', color: '#f59e0b' },
            { key: 'produksjon_svorkmo', label: 'Produksjon Svorkmo', color: '#92400e' },
            { key: 'rennebu_oppstroms', label: 'Rennebu oppstrøms grana', color: '#6b7280' },
            { key: 'nedstroms_svorkmo', label: 'Nedstrøms Svorkmo kraftverk', color: '#ec4899' }
        ].map(function(d) {
            return {
                label: d.label,
                data: data.map(function(item) {
                    const value = parseFloat(item[d.key]);
                    return {
                        x: item.timestamp,
                        y: isNaN(value) ? null : value
                    };
                }).filter(function(item) {
                    return item.y !== null;
                }),
                borderColor: d.color,
                backgroundColor: d.color + '1a',
                borderWidth: 2,
                fill: false,
                tension: 0.1
            };
        }).filter(function(dataset) {
            return dataset.data.length > 0;
        });

        if (datasets.length === 0) {
            showError('No valid data to display');
            return;
        }

        if (waterLevelChart) {
            waterLevelChart.destroy();
        }

        const ctx = document.getElementById('widget-chart').getContext('2d');

        waterLevelChart = new Chart(ctx, {
            type: 'line',
            data: { datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: 'HH:mm',
                                day: 'MMM dd',
                                week: 'MMM dd',
                                month: 'MMM yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Vannføring (m³/sek)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function(context) {
                                return new Date(context[0].parsed.x).toLocaleString();
                            },
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' m³/sek';
                            }
                        }
                    }
                }
            }
        });

        console.log('Chart rendered successfully');
    }

    function showError(message) {
        $('.widget-chart-container').html(
            '<div class="orkla-error" style="padding: 40px; text-align: center; background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; color: #721c24;">' +
            '<strong>⚠ ' + message + '</strong>' +
            '</div>'
        );
    }
});
