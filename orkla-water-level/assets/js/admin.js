jQuery(document).ready(function($) {
    console.log('Orkla Admin JS loaded');
    console.log('AJAX URL:', orkla_admin_ajax.ajax_url);
    console.log('Nonce:', orkla_admin_ajax.nonce);

    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is NOT loaded! Cannot render graphs.');
        $('.orkla-chart-container').html('<div style="padding: 40px; text-align: center; background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; color: #721c24;"><h3>⚠ Chart.js Not Loaded</h3><p>The Chart.js library failed to load from CDN. This may be due to:</p><ul style="text-align: left; max-width: 500px; margin: 20px auto;"><li>CDN blocked by firewall/adblocker</li><li>Network connection issue</li><li>Script loading order problem</li></ul><p><strong>Solution:</strong> Check browser console (F12) for errors.</p></div>');
        return;
    } else {
        console.log('Chart.js loaded successfully (version ' + Chart.version + ')');
    }

    let waterLevelChart = null;
    let chartData = [];

    // Initialize admin page
    if ($('#vannforing-chart').length) {
        console.log('Initializing admin chart');
        initializeAdminChart();
    }
    
    function initializeAdminChart() {
        const $periodSelect = $('#period-select');
        const defaultPeriod = orkla_admin_ajax.defaultPeriod || 'today';

        if ($periodSelect.length && defaultPeriod && $periodSelect.find(`option[value="${defaultPeriod}"]`).length) {
            $periodSelect.val(defaultPeriod);
        }

        const initialPeriod = defaultPeriod || 'today';
        let currentPeriod = initialPeriod;
        const locale = (window.orkla_admin_ajax && orkla_admin_ajax.locale) || document.documentElement.lang || 'nb-NO';
        const fallbackLocale = 'en-GB';

        function resolvePeriodKey(value) {
            if (!value) {
                return 'month';
            }
            return value.startsWith('year:') ? 'year-specific' : value;
        }

        function sanitizeIntlString(value) {
            if (typeof value !== 'string') {
                return value || '';
            }
            return value
                .replace(/[\u202f\u00a0]/g, ' ')
                .replace(/,/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim();
        }

        function toDate(value) {
            if (value instanceof Date) {
                return Number.isNaN(value.getTime()) ? null : value;
            }
            if (typeof value === 'number') {
                const numberDate = new Date(value);
                return Number.isNaN(numberDate.getTime()) ? null : numberDate;
            }
            if (typeof value === 'string') {
                const stringDate = new Date(value);
                return Number.isNaN(stringDate.getTime()) ? null : stringDate;
            }
            return null;
        }

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function createFormatter(options) {
            let primaryFormatter = null;
            try {
                primaryFormatter = new Intl.DateTimeFormat(locale.replace('_', '-'), options);
            } catch (error) {
                primaryFormatter = null;
            }

            const fallbackFormatter = primaryFormatter && locale.replace('_', '-') === fallbackLocale
                ? primaryFormatter
                : new Intl.DateTimeFormat(fallbackLocale, options);

            return function(date) {
                if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                    return '';
                }
                try {
                    if (primaryFormatter) {
                        return sanitizeIntlString(primaryFormatter.format(date));
                    }
                } catch (error) {
                    // Ignore and fall back
                }
                return sanitizeIntlString(fallbackFormatter.format(date));
            };
        }

        const weekdayFormatter = createFormatter({ weekday: 'short' });
        const dayMonthFormatter = createFormatter({ day: '2-digit', month: 'short' });
        const monthYearFormatter = createFormatter({ month: 'short', year: 'numeric' });
        const tooltipFormatter = createFormatter({ day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

        function formatHourLabel(value) {
            const date = toDate(value);
            if (!date) {
                return '';
            }
            return pad(date.getHours()) + ':00';
        }

        function formatWeekLabel(value) {
            const date = toDate(value);
            if (!date) {
                return '';
            }
            const weekday = weekdayFormatter(date);
            return (weekday ? weekday + ' ' : '') + formatHourLabel(date);
        }

        function formatDayLabel(value) {
            const date = toDate(value);
            if (!date) {
                return '';
            }
            return dayMonthFormatter(date);
        }

        function formatMonthLabel(value) {
            const date = toDate(value);
            if (!date) {
                return '';
            }
            return monthYearFormatter(date);
        }

        function formatTooltipTimestamp(value) {
            const date = toDate(value);
            if (!date) {
                return '';
            }
            return tooltipFormatter(date);
        }

        function buildTimeScaleOptions(periodValue) {
            const periodKey = resolvePeriodKey(periodValue);
            const base = {
                type: 'time',
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                time: {
                    tooltipFormat: 'yyyy-MM-dd HH:mm',
                    displayFormats: {
                        hour: 'HH:mm',
                        day: 'dd MMM',
                        week: 'dd MMM',
                        month: 'MMM yyyy'
                    }
                },
                ticks: {
                    autoSkip: true,
                    maxRotation: periodKey === 'today' ? 0 : 45,
                    minRotation: 0
                }
            };

            if (periodKey === 'today') {
                base.time.unit = 'hour';
                base.time.round = 'hour';
                base.time.stepSize = 1;
                base.time.minUnit = 'hour';
            }

            let tickFormatter = function(value) {
                return formatDayLabel(value);
            };

            switch (periodKey) {
                case 'today':
                    tickFormatter = formatHourLabel;
                    break;
                case 'week':
                    tickFormatter = formatWeekLabel;
                    break;
                case 'month':
                    tickFormatter = formatDayLabel;
                    break;
                case 'year':
                case 'year-specific':
                    tickFormatter = formatMonthLabel;
                    break;
                default:
                    tickFormatter = formatDayLabel;
            }

            base.ticks.callback = function(value, index, ticks) {
                const tick = ticks && ticks[index] ? ticks[index] : null;
                const tickValue = tick && typeof tick.value !== 'undefined' ? tick.value : value;
                return tickFormatter(tickValue);
            };

            return base;
        }

        // Load initial data
        loadChartData(initialPeriod);
        
        // Event listeners
        $('#period-select').on('change', function() {
            loadChartData($(this).val());
        });
        
        $('#refresh-data').on('click', function() {
            loadChartData($('#period-select').val());
        });
        
        $('#fetch-now').on('click', function() {
            fetchDataNow();
        });

        $('#dataset-select').on('change', function() {
            updateChart(chartData);
            updateStats(chartData);
        });
        
        function loadChartData(period) {
            console.log('Loading chart data for period:', period);
            
            // Show loading in stats
            $('#current-level, #avg-level, #max-level, #min-level').text('Loading...');
            currentPeriod = period;
            
            $.ajax({
                url: orkla_admin_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_water_data',
                    period: period,
                    nonce: orkla_admin_ajax.nonce
                },
                success: function(response) {
                    console.log('Admin AJAX response:', response);
                    if (response.success && response.data) {
                        chartData = response.data || [];
                        console.log('Chart data loaded, total records:', chartData.length);
                        if (chartData.length > 0) {
                            console.log('First record:', chartData[0]);
                            console.log('Last record:', chartData[chartData.length - 1]);
                        }
                        updateChart(chartData);
                        updateStats(chartData);
                    } else {
                        console.error('Error in response:', response);
                        showError('Error loading data: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Admin AJAX request failed:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    showError('Failed to load vannføring data: ' + error);
                }
            });
        }
        
        function updateChart(data) {
            console.log('Updating admin chart with data:', data);

            const container = $('.orkla-chart-container');
            container.find('.orkla-error').remove();
            container.find('.orkla-missing').remove();

            if (!data || data.length === 0) {
                showError('No data available');
                if (waterLevelChart) {
                    waterLevelChart.destroy();
                    waterLevelChart = null;
                }
                return;
            }

            const datasetType = $('#dataset-select').val() || 'flow';
            const config = datasetType === 'temperature'
                ? buildTemperatureDatasets(data)
                : buildFlowDatasets(data);

            if (!config.datasets || config.datasets.length === 0) {
                showError('No data available for the selected dataset.');
                if (waterLevelChart) {
                    waterLevelChart.destroy();
                    waterLevelChart = null;
                }
                return;
            }

            renderDatasetAvailability(config.missingSeries || []);

            if (waterLevelChart) {
                waterLevelChart.destroy();
            }

            const canvas = document.getElementById('vannforing-chart');
            if (!canvas) {
                showError('Chart container is missing');
                return;
            }

            waterLevelChart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    datasets: config.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: {
                        point: {
                            radius: 0,
                            hoverRadius: 4,
                            hitRadius: 8
                        }
                    },
                    scales: {
                        x: buildTimeScaleOptions(currentPeriod),
                        y: {
                            beginAtZero: config.beginAtZero,
                            suggestedMin: config.suggestedMin,
                            suggestedMax: config.suggestedMax,
                            title: {
                                display: true,
                                text: config.yLabel
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
                                padding: 15
                            }
                        },
                        tooltip: {
                            mode: 'x',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.3)',
                            borderWidth: 1,
                            callbacks: {
                                title: function(context) {
                                    const firstPoint = context && context.length ? context[0] : null;
                                    const timestamp = firstPoint ? firstPoint.parsed.x : undefined;
                                    return formatTooltipTimestamp(timestamp);
                                },
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + config.tooltipSuffix;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'x',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        function buildFlowDatasets(data) {
            console.log('buildFlowDatasets called with', data.length, 'records');
            
            const series = [
                {
                    label: 'Vannføring Oppstrøms Brattset',
                    field: 'vannforing_brattset',
                    borderColor: 'red',
                    backgroundColor: 'rgba(255, 0, 0, 0.1)'
                },
                {
                    label: 'Vannføring Syrstad',
                    field: 'vannforing_syrstad',
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 0, 255, 0.1)'
                },
                {
                    label: 'Vannføring Storsteinshølen',
                    field: 'vannforing_storsteinsholen',
                    borderColor: 'purple',
                    backgroundColor: 'rgba(128, 0, 128, 0.1)'
                },
                {
                    label: 'Produksjonsvannføring Brattset',
                    field: 'produksjon_brattset',
                    borderColor: 'green',
                    backgroundColor: 'rgba(0, 128, 0, 0.1)'
                },
                {
                    label: 'Produksjonsvannføring Grana',
                    field: 'produksjon_grana',
                    borderColor: 'orange',
                    backgroundColor: 'rgba(255, 165, 0, 0.1)'
                },
                {
                    label: 'Produksjon Svorkmo',
                    field: 'produksjon_svorkmo',
                    borderColor: 'brown',
                    backgroundColor: 'rgba(165, 42, 42, 0.1)'
                },
                {
                    label: 'Rennebu oppstrøms grana',
                    field: 'rennebu_oppstroms',
                    borderColor: 'gray',
                    backgroundColor: 'rgba(128, 128, 128, 0.1)'
                },
                {
                    label: 'Nedstrøms Svorkmo kraftverk',
                    field: 'nedstroms_svorkmo',
                    borderColor: 'pink',
                    backgroundColor: 'rgba(255, 192, 203, 0.1)'
                }
            ];

            const datasets = [];
            const missingSeries = [];

            series.forEach(def => {
                const points = data
                    .map(item => ({
                        x: item.timestamp,
                        y: parseFloat(item[def.field])
                    }))
                    .filter(item => Number.isFinite(item.y));

                console.log('Series:', def.label, '- Points:', points.length);
                if (points.length > 0) {
                    console.log('  First point:', points[0]);
                    console.log('  Last point:', points[points.length - 1]);
                }

                if (points.length > 0) {
                    datasets.push({
                        label: def.label,
                        data: points,
                        borderColor: def.borderColor,
                        backgroundColor: def.backgroundColor,
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHitRadius: 8
                    });
                } else {
                    missingSeries.push(def.label);
                }
            });

            return {
                datasets: datasets,
                missingSeries: missingSeries,
                yLabel: 'Vannføring (m³/sek)',
                tooltipSuffix: ' m³/sek',
                beginAtZero: true
            };
        }

        function buildTemperatureDatasets(data) {
            const series = [
                {
                    label: 'Vanntemperatur Syrstad',
                    field: 'vanntemperatur_syrstad',
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)'
                }
            ];

            const datasets = [];
            const missingSeries = [];

            series.forEach(def => {
                const points = data
                    .map(item => ({
                        x: item.timestamp,
                        y: parseFloat(item[def.field])
                    }))
                    .filter(item => Number.isFinite(item.y));

                if (points.length > 0) {
                    datasets.push({
                        label: def.label,
                        data: points,
                        borderColor: def.borderColor,
                        backgroundColor: def.backgroundColor,
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHitRadius: 8
                    });
                } else {
                    missingSeries.push(def.label);
                }
            });

            return {
                datasets: datasets,
                missingSeries: missingSeries,
                yLabel: 'Vanntemperatur (°C)',
                tooltipSuffix: ' °C',
                beginAtZero: false,
                suggestedMin: 0
            };
        }
        
        function updateStats(data) {
            if (!data || data.length === 0) {
                $('#current-level, #avg-level, #max-level, #min-level').text('--');
                return;
            }

            const datasetType = $('#dataset-select').val() || 'flow';
            const latest = data[data.length - 1];

            if (datasetType === 'temperature') {
                const temperatureValues = data
                    .map(item => parseFloat(item.vanntemperatur_syrstad))
                    .filter(v => Number.isFinite(v));

                const currentTempRaw = parseFloat(latest.vanntemperatur_syrstad);

                if (temperatureValues.length === 0 || !Number.isFinite(currentTempRaw)) {
                    $('#current-level, #avg-level, #max-level, #min-level').text('--');
                    return;
                }

                const max = Math.max(...temperatureValues);
                const min = Math.min(...temperatureValues);
                const avg = temperatureValues.reduce((acc, val) => acc + val, 0) / temperatureValues.length;

                $('#current-level').text(currentTempRaw.toFixed(1) + ' °C');
                $('#avg-level').text(avg.toFixed(1) + ' °C');
                $('#max-level').text(max.toFixed(1) + ' °C');
                $('#min-level').text(min.toFixed(1) + ' °C');
                return;
            }

            const currentRaw = parseFloat(latest.vannforing_brattset);
            if (!Number.isFinite(currentRaw)) {
                $('#current-level, #avg-level, #max-level, #min-level').text('--');
                return;
            }

            const brattsetValues = data
                .map(item => parseFloat(item.vannforing_brattset))
                .filter(v => Number.isFinite(v) && v > 0);

            if (brattsetValues.length === 0) {
                $('#current-level, #avg-level, #max-level, #min-level').text('--');
                return;
            }

            const max = Math.max(...brattsetValues);
            const min = Math.min(...brattsetValues);
            const avg = brattsetValues.reduce((a, b) => a + b, 0) / brattsetValues.length;

            $('#current-level').text(currentRaw.toFixed(2) + ' m³/sek');
            $('#avg-level').text(avg.toFixed(2) + ' m³/sek');
            $('#max-level').text(max.toFixed(2) + ' m³/sek');
            $('#min-level').text(min.toFixed(2) + ' m³/sek');
        }
        
        function fetchDataNow() {
            const button = $('#fetch-now');
            button.prop('disabled', true).text('Fetching...');
            console.log('Fetching data now...');
            
            $.ajax({
                url: orkla_admin_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'fetch_csv_data_now',
                    nonce: orkla_admin_ajax.nonce
                },
                success: function(response) {
                    console.log('Fetch now response:', response);
                    if (response.success) {
                        const payload = response.data || {};
                        const message = payload.message || 'CSV data processed.';
                        const recordInfo = typeof payload.total_records !== 'undefined'
                            ? ` (Total records: ${payload.total_records})`
                            : '';
                        alert(message + recordInfo);
                        loadChartData($('#period-select').val());
                    } else {
                        const errorMessage = response.data && response.data.message
                            ? response.data.message
                            : response.data;
                        alert('Error fetching data: ' + errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Failed to fetch data: ' + error);
                    console.error('Fetch now failed:', xhr, status, error);
                },
                complete: function() {
                    button.prop('disabled', false).text('Fetch Now');
                }
            });
        }
        
        function showError(message) {
            console.error('Admin error:', message);
            $('#current-level, #avg-level, #max-level, #min-level').text('--');

            const container = $('.orkla-chart-container');
            container.find('.orkla-error').remove();
            container.find('.orkla-missing').remove();
            container.append('<div class="orkla-error">' + message + '</div>');
        }

        function renderDatasetAvailability(missingSeries) {
            const container = $('.orkla-chart-container');
            container.find('.orkla-missing').remove();

            if (!missingSeries || missingSeries.length === 0) {
                return;
            }

            const message = 'Ingen data for valgt periode for: ' + missingSeries.join(', ');
            container.append('<div class="orkla-missing">' + message + '</div>');
        }
    }
});