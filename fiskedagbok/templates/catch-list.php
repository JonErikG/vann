<div class="fiskedagbok-list-container">
    <h3>Mine fangster</h3>
    
    <!-- År filter -->
    <div class="year-filter-container">
        <label for="year-filter">Velg år:</label>
        <select id="year-filter" onchange="filterByYear(this.value)">
            <option value="">Alle år</option>
            <?php if (!empty($available_years)): ?>
                <?php foreach ($available_years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>" <?php echo (isset($_GET['year']) && $_GET['year'] == $year) ? 'selected' : ''; ?>>
                        <?php echo esc_html($year); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    
    <?php if (!empty($catches)): ?>
    <div class="catches-summary">
        <p>Du har registrert <strong><?php echo count($catches); ?></strong> fangster<?php echo isset($_GET['year']) ? ' i ' . esc_html($_GET['year']) : ''; ?>.</p>
    </div>
    
    <div class="catches-list" id="catches-list">
        <?php foreach ($catches as $catch): ?>
        <div class="catch-item" data-catch-id="<?php echo esc_attr($catch->id); ?>">
            <div class="catch-header">
                <span class="catch-date"><?php echo esc_html(date('d.m.Y', strtotime($catch->date))); ?></span>
                <?php if ($catch->time_of_day): ?>
                <span class="catch-time"><?php echo esc_html(date('H:i', strtotime($catch->time_of_day))); ?></span>
                <?php endif; ?>
                <span class="catch-fish-type"><?php echo esc_html($catch->fish_type); ?></span>
                <?php if (!empty($catch->weather_data)): ?>
                <span class="weather-icon" title="Værdata tilgjengelig">🌤️</span>
                <?php endif; ?>
            </div>
            
            <div class="catch-details">
                <div class="catch-location">
                    <?php if ($catch->river_name): ?>
                    <strong>Elv:</strong> <?php echo esc_html($catch->river_name); ?>
                    <?php endif; ?>
                    
                    <?php if ($catch->beat_name): ?>
                    | <strong>Beat:</strong> <?php echo esc_html($catch->beat_name); ?>
                    <?php endif; ?>
                    
                    <?php if ($catch->fishing_spot): ?>
                    | <strong>Sted:</strong> <?php echo esc_html($catch->fishing_spot); ?>
                    <?php endif; ?>
                </div>
                
                <div class="catch-specs">
                    <?php if ($catch->weight_kg): ?>
                    <span class="weight"><strong>Vekt:</strong> <?php echo esc_html($catch->weight_kg); ?> kg</span>
                    <?php endif; ?>
                    
                    <?php if ($catch->length_cm): ?>
                    <span class="length"><strong>Lengde:</strong> <?php echo esc_html($catch->length_cm); ?> cm</span>
                    <?php endif; ?>
                    
                    <?php if ($catch->equipment): ?>
                    <span class="equipment"><strong>Utstyr:</strong> <?php echo esc_html($catch->equipment); ?></span>
                    <?php endif; ?>

                    <?php
                        $storedTempFresh = false;
                        if ($catch->water_temperature !== null && !empty($catch->water_temperature_recorded_at)) {
                            $storedTempFresh = date('Y-m-d', strtotime($catch->water_temperature_recorded_at)) === $catch->date;
                        }

                    ?>
                    <?php if ($storedTempFresh): ?>
                    <span class="water-temperature"><strong>Vanntemp:</strong> <?php echo esc_html(number_format((float) $catch->water_temperature, 1, ',', '')); ?> °C</span>
                    <?php else: ?>
                    <span class="water-temperature"><strong>Vanntemp:</strong> Ingen måling denne dato enda</span>
                    <?php endif; ?>
                    
                    <!-- Tidewater info -->
                    <span class="tidewater-info" data-catch-id="<?php echo esc_attr($catch->id); ?>" style="display: none;">
                        <strong>Tidevann:</strong> <span class="tide-text">Laster...</span>
                    </span>
                </div>
                
                <div class="catch-meta">
                    <?php if ($catch->sex && $catch->sex !== 'unknown'): ?>
                    <span class="sex"><strong>Kjønn:</strong> <?php echo $catch->sex === 'male' ? 'Hann' : 'Hunn'; ?></span>
                    <?php endif; ?>
                    
                    <span class="released <?php echo $catch->released ? 'yes' : 'no'; ?>">
                        <?php echo $catch->released ? '✓ Sluppet' : '✗ Tatt med'; ?>
                    </span>
                    
                </div>
                
                <?php if ($catch->notes): ?>
                <div class="catch-notes">
                    <strong>Notater:</strong> <?php echo esc_html($catch->notes); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="catch-actions">
                <button class="view-details-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Se detaljer</button>
                <button class="edit-catch-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Rediger</button>
                <button class="delete-catch-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>">Slett</button>
                <button class="view-details-debug-btn" data-catch-id="<?php echo esc_attr($catch->id); ?>" style="font-size: 11px; padding: 4px 8px; opacity: 0.6;" title="Test med dummy-data">🐛 Test</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Edit Modal -->
    <div id="edit-catch-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Rediger fangst</h3>
            <form id="edit-catch-form">
                <input type="hidden" id="edit-catch-id" name="catch_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-date">Dato</label>
                        <input type="date" id="edit-date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-time">Tid</label>
                        <input type="time" id="edit-time" name="time_of_day">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit-fish-type">Fisketype</label>
                    <select id="edit-fish-type" name="fish_type" required>
                        <option value="Laks">Laks</option>
                        <option value="Sjøørret">Sjøørret</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-river">Elv</label>
                        <input type="text" id="edit-river" name="river_name">
                    </div>
                    <div class="form-group">
                        <label for="edit-beat">Beat</label>
                        <input type="text" id="edit-beat" name="beat_name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit-spot">Fiskeplass</label>
                    <input type="text" id="edit-spot" name="fishing_spot">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-equipment">Utstyr</label>
                        <input type="text" id="edit-equipment" name="equipment">
                    </div>
                    <div class="form-group">
                        <label for="edit-fly-lure">Flue/Sluk</label>
                        <input type="text" id="edit-fly-lure" name="fly_lure" placeholder="Hvilken flue eller sluk ble brukt">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-weight">Vekt (kg)</label>
                        <input type="number" id="edit-weight" name="weight_kg" step="0.1" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit-length">Lengde (cm)</label>
                        <input type="number" id="edit-length" name="length_cm" step="0.1" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit-released" name="released" value="1">
                        Sluppet tilbake
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="edit-sex">Kjønn</label>
                    <select id="edit-sex" name="sex">
                        <option value="">-- Ikke valgt --</option>
                        <option value="hann">Hann</option>
                        <option value="hunn">Hunn</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-notes">Notater</label>
                    <textarea id="edit-notes" name="notes" rows="3" placeholder="Ekstra notater om fangsten..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-water-station">🌊 Vannføring stasjon (valgfritt)</label>
                    <select id="edit-water-station" name="water_station_override">
                        <option value="">-- Bruk standard for beat --</option>
                        <option value="storsteinsholen">Vannføring Storsteinshølen</option>
                        <option value="rennebu_oppstroms">Rennebu oppstrøms Grana</option>
                        <option value="syrstad">Vannføring Syrstad</option>
                        <option value="nedstroms_svorkmo">Nedstrøms Svorkmo kraftverk</option>
                        <option value="oppstroms_brattset">Vannføring oppstrøms Brattset</option>
                    </select>
                    <small>Velg spesifikk vannføring stasjon for denne fangsten (overstyrer standard beat konfiguration)</small>
                </div>
                
                <div class="form-group">
                    <button type="submit">Lagre endringer</button>
                    <button type="button" class="cancel-edit">Avbryt</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="no-catches">
        <p>Du har ikke registrert noen fangster ennå.</p>
        <p>Bruk skjemaet ovenfor for å registrere din første fangst!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Detaljert fangst modal -->
<div id="catch-details-modal" class="modal catch-details-modal" style="display: none;">
    <div class="modal-content large">
        <span class="close">&times;</span>
        <div id="catch-details-content">
            <!-- Fangst detaljer vil bli lagt til her -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialiser alle event handlers
    initializeCatchListEvents();
    
    // Submit edit form
    $('#edit-catch-form').submit(function(e) {
        e.preventDefault();
        updateCatch();
    });
    
    function loadCatchForEdit(catchId) {
        // Load catch data via AJAX and populate form
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var catchData = response.data;
                    $('#edit-catch-id').val(catchData.id);
                    $('#edit-date').val(catchData.date);
                    $('#edit-time').val(catchData.time_of_day);
                    $('#edit-fish-type').val(catchData.fish_type);
                    $('#edit-weight').val(catchData.weight_kg);
                    $('#edit-length').val(catchData.length_cm);
                    $('#edit-released').prop('checked', catchData.released == 1);
                    $('#edit-river').val(catchData.river_name || '');
                    $('#edit-beat').val(catchData.beat_name || '');
                    $('#edit-spot').val(catchData.fishing_spot || '');
                    $('#edit-equipment').val(catchData.equipment || '');
                    $('#edit-fly-lure').val(catchData.fly_lure || '');
                    $('#edit-sex').val(catchData.sex || '');
                    $('#edit-notes').val(catchData.notes || '');
                    $('#edit-water-station').val(catchData.water_station_override || '');
                }
            }
        });
    }
    
    function updateCatch() {
        var formData = $('#edit-catch-form').serialize();
        formData += '&action=update_catch&nonce=' + fiskedagbok_ajax.nonce;
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#edit-catch-modal').hide();
                    location.reload(); // Reload to show updated data
                } else {
                    alert('Feil ved oppdatering: ' + response.data);
                }
            }
        });
    }
    
    function deleteCatch(catchId) {
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_catch',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('[data-catch-id="' + catchId + '"]').fadeOut();
                } else {
                    alert('Feil ved sletting: ' + response.data);
                }
            }
        });
    }
    
    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function ensureCatchDetailsModal() {
        var $modal = $('#catch-details-modal');

        if ($modal.length === 0) {
            console.warn('Modal not found, creating a new one dynamically');
            $('body').append(
                '<div id="catch-details-modal" class="modal catch-details-modal" style="display: none;">' +
                    '<div class="modal-content large">' +
                        '<span class="close">&times;</span>' +
                        '<div id="catch-details-content"></div>' +
                    '</div>' +
                '</div>'
            );
            $modal = $('#catch-details-modal');
        }

        var $modalContent = $modal.find('.modal-content');

        if (!$modal.data('original-html')) {
            $modal.data('original-html', $modalContent.html());
        }

        if ($modal.find('#catch-details-content').length === 0) {
            var original = $modal.data('original-html');
            if (original) {
                $modalContent.html(original);
            } else {
                $modalContent.html('<span class="close">&times;</span><div id="catch-details-content"></div>');
            }
        }

        $modal.find('.close').off('click').on('click', function() {
            $modal.fadeOut();
        });

        return $modal;
    }
    
    function viewCatchDetails(catchId) {
        console.log('viewCatchDetails', catchId);
        
        // Ensure modal structure exists and show loading state
        var $modal = ensureCatchDetailsModal();
        var $contentArea = $modal.find('#catch-details-content');

        if ($contentArea.length === 0) {
            console.error('Kunne ikke finne #catch-details-content selv etter ensureCatchDetailsModal');
            return;
        }

        $contentArea.html('<div style="padding: 40px; text-align: center;"><p>Laster detaljer...</p><div class="spinner"></div></div>');
    $modal.fadeIn();

        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',  // Ensure response is parsed as JSON
            data: {
                action: 'get_catch_details',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            timeout: 10000, // 10 second timeout (was 30) - backend should be very fast with optimizations
            success: function(response) {
                console.log('get_catch_details FULL response:', response);
                console.log('get_catch_details response type:', typeof response);
                console.log('get_catch_details response.success:', response.success);
                console.log('get_catch_details response.data:', response.data);
                console.log('get_catch_details response.data type:', typeof response.data);
                if (response.success && response.data) {
                    console.log('Catch details loaded successfully, calling displayCatchDetails...');
                    displayCatchDetails(response.data, false);  // Pass false for isTestData
                } else {
                    console.error('Response success is false or no data:', response);
                    $contentArea.html('<p style="color: #d63638;">Backend error: ' + (response.data || 'Unknown error') + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error fetching catch details:', textStatus, errorThrown);
                console.error('jqXHR.status:', jqXHR.status);
                console.error('jqXHR.statusText:', jqXHR.statusText);
                console.error('jqXHR.responseText:', jqXHR.responseText);
                var errorMsg = textStatus === 'timeout' ? 'Tidsavbrudd (> 10 sekunder)' : textStatus;
                $contentArea.html('<p style="color: #d63638;">Feil ved henting av detaljer: ' + errorMsg + '</p><p style="font-size: 12px; color: #666;">' + jqXHR.statusText + '</p>');
            }
        });
    }
    
    /**
     * DEBUG: Fetch catch details with test data (no backend call)
     * Use this to test if the modal rendering itself is slow
     */
    function viewCatchDetailsDebug(catchId) {
        console.log('viewCatchDetailsDebug', catchId);
        
        var $modal = ensureCatchDetailsModal();
        var $contentArea = $modal.find('#catch-details-content');

        if ($contentArea.length === 0) {
            console.error('Kunne ikke finne #catch-details-content i debug-modus');
            return;
        }

        $contentArea.html('<div style="padding: 40px; text-align: center;"><p>Laster TEST-detaljer...</p><div class="spinner"></div></div>');
    $modal.fadeIn();

        // Simulate fast response with test data
        setTimeout(function() {
            var testData = {
                id: catchId,
                fish_type: 'Laks (TEST)',
                weight_kg: '4.5',
                length_cm: '65',
                sex: 'male',
                released: 0,
                date: '2025-10-30',
                time_of_day: '14:30:00',
                river_name: 'Orkla',
                beat_name: 'Holstad',
                fishing_spot: 'Hovedfossen',
                week: '44',
                equipment: 'Flue',
                notes: 'TEST DATA - UI rendering test',
                weather: null
            };
            console.log('DEBUG: Using test data for catch', testData);
            displayCatchDetails(testData, true);  // Pass true for isTestData
        }, 100);
    }
    
    function displayCatchDetails(catchData, isTestData) {
        console.log('displayCatchDetails called with catch_data:', catchData);
        console.log('displayCatchDetails isTestData:', isTestData);
        
        var $modal = ensureCatchDetailsModal();
        var $contentDiv = $modal.find('#catch-details-content');

        if ($contentDiv.length === 0) {
            console.error('ERROR: #catch-details-content ikke funnet i displayCatchDetails');
            return;
        }
        
        try {
            function formatNumberLabel(value, unit) {
                var num = parseFloat(value);
                if (isNaN(num)) {
                    return 'Ikke oppgitt';
                }

                var decimals = Math.abs(num % 1) < 0.01 ? 0 : 1;
                var formatted = num.toFixed(decimals);
                return escapeHtml(formatted) + (unit ? ' ' + unit : '');
            }

            function fallbackText(value, fallback) {
                var safe = escapeHtml(value);
                return safe || fallback;
            }

            var catchIdAttr = escapeHtml(catchData.id || '');
            var catchDateAttr = escapeHtml(catchData.date || '');

            var fishType = fallbackText(catchData.fish_type, 'Ukjent fisk');
            var catchDate = fallbackText(catchData.date, 'Ukjent dato');

            var timeOfDay = catchData.time_of_day ? catchData.time_of_day.toString() : '';
            var catchTime = timeOfDay ? escapeHtml(timeOfDay.substring(0, 5)) : 'Ikke oppgitt';
            var catchDateTimeIso = null;

            if (catchData.date) {
                var rawTime = timeOfDay;
                if (!rawTime) {
                    rawTime = '12:00:00';
                } else if (rawTime.length === 5) {
                    rawTime += ':00';
                }

                var normalized = catchData.date + 'T' + rawTime.substring(0, 8);
                var normalizedDate = new Date(normalized);
                if (!isNaN(normalizedDate.getTime())) {
                    catchDateTimeIso = normalized;
                }
            }

            var riverDisplay = fallbackText(catchData.river_name, 'Ikke oppgitt');
            var beatDisplay = fallbackText(catchData.beat_name, 'Ikke oppgitt');
            var fishingSpot = fallbackText(catchData.fishing_spot, 'Ikke oppgitt');
            var weekDisplay = fallbackText(catchData.week, 'Ikke oppgitt');

            var weightDisplay = formatNumberLabel(catchData.weight_kg, 'kg');
            var lengthDisplay = formatNumberLabel(catchData.length_cm, 'cm');

            var releaseLabel = 'Ukjent';
            if (catchData.released === 1 || catchData.released === '1') {
                releaseLabel = 'Sluppet tilbake';
            } else if (catchData.released === 0 || catchData.released === '0') {
                releaseLabel = 'Tatt med';
            }

            var sexLabel = 'Ukjent';
            if (catchData.sex === 'male') {
                sexLabel = 'Hann';
            } else if (catchData.sex === 'female') {
                sexLabel = 'Hunn';
            } else if (catchData.sex) {
                sexLabel = fallbackText(catchData.sex, 'Ukjent');
            }

            var equipmentDisplay = fallbackText(catchData.equipment, 'Ikke oppgitt');

            var subtitleParts = [];
            if (catchTime !== 'Ikke oppgitt') {
                subtitleParts.push('Kl. ' + catchTime);
            }
            if (beatDisplay !== 'Ikke oppgitt') {
                subtitleParts.push('Beat: ' + beatDisplay);
            }
            if (riverDisplay !== 'Ikke oppgitt') {
                subtitleParts.push('Elv: ' + riverDisplay);
            }

            var headerHtml = '<h3>' + fishType + ' – ' + catchDate + '</h3>';
            if (subtitleParts.length) {
                headerHtml += '<p class="details-subtitle">' + subtitleParts.join(' • ') + '</p>';
            }

            var locationHtml = '<div class="details-section">' +
                '<h4>📍 Sted og tid</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Elv:</strong> ' + riverDisplay + '</span>' +
                    '<span><strong>Beat:</strong> ' + beatDisplay + '</span>' +
                    '<span><strong>Fiskeplass:</strong> ' + fishingSpot + '</span>' +
                    '<span><strong>Tid:</strong> ' + catchTime + '</span>' +
                    '<span><strong>Uke:</strong> ' + weekDisplay + '</span>' +
                '</div>' +
            '</div>';

            var fishDetailsHtml = '<div class="details-section">' +
                '<h4>🐟 Fangstdetaljer</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Type:</strong> ' + fishType + '</span>' +
                    '<span><strong>Vekt:</strong> ' + weightDisplay + '</span>' +
                    '<span><strong>Lengde:</strong> ' + lengthDisplay + '</span>' +
                    '<span><strong>Kjønn:</strong> ' + sexLabel + '</span>' +
                    '<span><strong>Status:</strong> ' + releaseLabel + '</span>' +
                '</div>' +
            '</div>';

            var equipmentHtml = '<div class="details-section">' +
                '<h4>🎣 Utstyr</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Utstyr:</strong> ' + equipmentDisplay + '</span>' +
                '</div>' +
            '</div>';

            var weather = catchData.weather || null;
            var weatherHtml = '';

            if (weather && typeof weather === 'object') {
                var weatherDetails = [];

                if (weather.summary) {
                    weatherDetails.push('<span>' + fallbackText(weather.summary, '') + '</span>');
                }
                if (weather.temperature !== null && weather.temperature !== undefined && weather.temperature !== '') {
                    weatherDetails.push('<span>Temperatur: ' + escapeHtml(weather.temperature) + ' °C</span>');
                }
                if (weather.precipitation !== null && weather.precipitation !== undefined && weather.precipitation !== '') {
                    weatherDetails.push('<span>Nedbør: ' + escapeHtml(weather.precipitation) + ' mm</span>');
                }
                if (weather.wind_speed !== null && weather.wind_speed !== undefined && weather.wind_speed !== '') {
                    weatherDetails.push('<span>Vind: ' + escapeHtml(weather.wind_speed) + ' m/s</span>');
                }
                if (weather.wind_direction !== null && weather.wind_direction !== undefined && weather.wind_direction !== '') {
                    weatherDetails.push('<span>Vindretning: ' + escapeHtml(weather.wind_direction) + '°</span>');
                }
                if (weather.cloud_cover !== null && weather.cloud_cover !== undefined && weather.cloud_cover !== '') {
                    weatherDetails.push('<span>Skydekke: ' + escapeHtml(weather.cloud_cover) + '%</span>');
                }

                var weatherMetaParts = [];
                if (weather.station) {
                    weatherMetaParts.push('Stasjon: ' + fallbackText(weather.station, '')); 
                }
                if (weather.source) {
                    weatherMetaParts.push('Kilde: ' + fallbackText(weather.source, ''));
                }
                if (weather.fetched_at) {
                    weatherMetaParts.push('Hentet: ' + fallbackText(weather.fetched_at, ''));
                }

                weatherHtml = '<div class="details-section weather-section">' +
                    '<h4>🌤️ Værforhold</h4>' +
                    (weatherDetails.length ? '<div class="weather-details">' + weatherDetails.join('') + '</div>' : '<p>Ingen detaljerte værdata tilgjengelig.</p>') +
                    (weatherMetaParts.length ? '<p style="font-size: 13px; color: #1e3a8a;">' + weatherMetaParts.join(' • ') + '</p>' : '');

                if (!isTestData) {
                    weatherHtml += '<div class="weather-actions">' +
                        '<button class="update-weather-btn" data-catch-id="' + catchIdAttr + '" data-date="' + catchDateAttr + '">🌤️ Oppdater værdata</button>' +
                    '</div>';
                }

                weatherHtml += '</div>';
            } else {
                weatherHtml = '<div class="details-section weather-section">' +
                    '<h4>🌤️ Værforhold</h4>' +
                    '<p>Værdata ikke tilgjengelig for denne fangsten ennå.</p>' +
                    (!isTestData ? '<button class="fetch-weather-btn" data-catch-id="' + catchIdAttr + '" data-date="' + catchDateAttr + '">Hent værdata</button>' : '') +
                '</div>';
            }

            var waterLevelSection = '<div class="details-section water-level-section">' +
                '<h4>🌊 Vannføring</h4>' +
                (isTestData ? '<p>Testmodus: Vannføringsdata hentes ikke.</p>' : '<p>Laster vannføringsdata...</p>') +
            '</div>';

            var tidewaterSection = '';
            if (!isTestData) {
                tidewaterSection = '<div class="details-section tidewater-section" id="modal-tidewater-section" style="display: none;">' +
                    '<h4>🌊 Tidevannsdata</h4>' +
                    '<div id="modal-tidewater-content"><p>Leter etter nærmeste tidevannsdata...</p></div>' +
                '</div>';
            }

            var notesHtml = '';
            if (catchData.notes) {
                var formattedNotes = escapeHtml(catchData.notes).replace(/\n/g, '<br>');
                notesHtml = '<div class="details-section">' +
                    '<h4>📝 Notater</h4>' +
                    '<p>' + formattedNotes + '</p>' +
                '</div>';
            }

            var actionsHtml = '<div class="details-actions">' +
                '<button class="close-details-btn">Lukk</button>' +
            '</div>';

            var detailsHtml = '<div class="catch-details-full">' +
                headerHtml +
                locationHtml +
                fishDetailsHtml +
                equipmentHtml +
                weatherHtml +
                waterLevelSection +
                tidewaterSection +
                notesHtml +
                actionsHtml +
            '</div>';

            $contentDiv.html(detailsHtml);

            if (!isTestData) {
                if (typeof loadModalWaterLevelData === 'function' && catchData.id) {
                    loadModalWaterLevelData(catchData.id);
                }
                if (typeof loadModalTidewaterData === 'function' && catchData.id) {
                    var $tideSection = $modal.find('#modal-tidewater-section');
                    if ($tideSection.length) {
                        $tideSection.show();
                    }
                    loadModalTidewaterData(catchData.id, catchDateTimeIso);
                }
            } else {
                var $testTideSection = $modal.find('#modal-tidewater-section');
                if ($testTideSection.length) {
                    $testTideSection.hide();
                }
            }

            if (!isTestData) {
                $modal.find('.fetch-weather-btn').off('click.fetchWeather').on('click.fetchWeather', function() {
                    var btnCatchId = $(this).data('catch-id');
                    var btnDate = $(this).data('date');
                    fetchWeatherData(btnCatchId, btnDate);
                });

                $modal.find('.update-weather-btn').off('click.updateWeather').on('click.updateWeather', function() {
                    var btnCatchId = $(this).data('catch-id');
                    var btnDate = $(this).data('date');
                    updateWeatherData(btnCatchId, btnDate);
                });
            }

        } catch (e) {
            console.error('Error in displayCatchDetails:', e);
            console.error('Stack trace:', e.stack);
            $contentDiv.html('<p style="color: red;">Feil ved visning av detaljer: ' + e.message + '</p>');
        }
        
        // Close modal event binding
        $modal.find('.close-details-btn').off('click.details').on('click.details', function() {
            $('#catch-details-modal').fadeOut();
        });
    }
    
    function fetchWeatherData(catchId, date) {
        $('.fetch-weather-btn').text('Henter...').prop('disabled', true);
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_weather_data',
                catch_id: catchId,
                date: date,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update weather icon in the list if it doesn't exist
                    var catchItem = $('.catch-item[data-catch-id="' + catchId + '"]');
                    if (catchItem.find('.weather-icon').length === 0) {
                        catchItem.find('.catch-header').append('<span class="weather-icon" title="Værdata tilgjengelig">🌤️</span>');
                    }
                    viewCatchDetails(catchId);
                } else {
                    alert('Kunne ikke hente værdata: ' + response.data);
                    $('.fetch-weather-btn').text('Hent værdata').prop('disabled', false);
                }
            },
            error: function() {
                alert('Feil ved henting av værdata');
                $('.fetch-weather-btn').text('Hent værdata').prop('disabled', false);
            }
        });
    }
    
    function updateWeatherData(catchId, date) {
        $('.update-weather-btn').text('Oppdaterer...').prop('disabled', true);
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'refresh_weather_data',
                catch_id: catchId,
                date: date,
                force_update: true,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Værdata oppdatert!');
                    // Update weather icon in the list if it doesn't exist
                    var catchItem = $('.catch-item[data-catch-id="' + catchId + '"]');
                    if (catchItem.find('.weather-icon').length === 0) {
                        catchItem.find('.catch-header').append('<span class="weather-icon" title="Værdata tilgjengelig">🌤️</span>');
                    }
                    // Reload the page to show updated weather data
                    location.reload();
                } else {
                    alert('Kunne ikke oppdatere værdata: ' + response.data);
                    $('.update-weather-btn').text('🌤️ Oppdater værdata').prop('disabled', false);
                }
            },
            error: function() {
                alert('Feil ved oppdatering av værdata');
                $('.update-weather-btn').text('🌤️ Oppdater værdata').prop('disabled', false);
            }
        });
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('no-NO');
    }
    
    function formatDateTime(dateTimeString) {
        var date = new Date(dateTimeString);
        return date.toLocaleDateString('no-NO') + ' ' + date.toLocaleTimeString('no-NO', {hour: '2-digit', minute: '2-digit'});
    }
    
    function loadModalTidewaterData(catchId, catchDateTimeIso) {
        var $tideContent = $('#modal-tidewater-content');
        $tideContent.empty().html('<p>Laster tidevannsdata...</p>');

        var catchTimestamp = null;
        var catchYear = null;
        if (catchDateTimeIso) {
            var parsedCatchTs = new Date(catchDateTimeIso);
            if (!isNaN(parsedCatchTs.getTime())) {
                catchTimestamp = parsedCatchTs;
                catchYear = parsedCatchTs.getFullYear();
            }
        }

        // Hvis fangsten er før 2022, vis info om manglende tidevannsdata
        if (catchYear && catchYear < 2022) {
            $tideContent.html('<div><strong>Tidevannsdata:</strong> Ikke tilgjengelig for fangster før 2022.</div>');
            return;
        }
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_tidewater',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            timeout: 5000,  // 5 second timeout - fail fast
            success: function(response) {
                if (response.success && response.data && response.data.data_points) {
                    var dataPointsRaw = response.data.data_points;
                    var stationName = response.data.station_name || 'Ukjent';
                    var stationCode = null;

                    if (response.data.station_code) {
                        stationCode = response.data.station_code;
                    } else if (Array.isArray(dataPointsRaw) && dataPointsRaw.length && dataPointsRaw[0].station_code) {
                        stationCode = dataPointsRaw[0].station_code;
                    }

                    if (!Array.isArray(dataPointsRaw) || dataPointsRaw.length === 0) {
                        $tideContent.html('<p>Ingen tidevannsdata tilgjengelig</p>');
                        return;
                    }

                    function parsePoint(point) {
                        var rawTs = point.timestamp ? point.timestamp.replace(' ', 'T') : '';
                        var parsedTs = rawTs ? new Date(rawTs) : null;
                        var levelNumeric = typeof point.water_level === 'number' ? point.water_level : parseFloat(point.water_level);
                        var roleList = [];

                        if (point.role) {
                            var roleString = point.role;
                            if (typeof roleString === 'string') {
                                if (roleString.indexOf('role:') === 0) {
                                    roleString = roleString.substring(5);
                                }
                                roleList = roleString.split(',').map(function(token) {
                                    return token.trim();
                                }).filter(function(token) {
                                    return token.length > 0;
                                });
                            }
                        }
                        return {
                            raw: point,
                            timestamp: parsedTs,
                            level: levelNumeric,
                            roles: roleList
                        };
                    }

                    var dataPoints = dataPointsRaw.map(parsePoint).filter(function(p) {
                        return p.timestamp && !isNaN(p.timestamp.getTime());
                    });

                    dataPoints.sort(function(a, b) {
                        return a.timestamp - b.timestamp;
                    });

                    var highPoints = dataPoints.filter(function(p) { return p.roles.includes('high'); });
                    var lowPoints = dataPoints.filter(function(p) { return p.roles.includes('low'); });

                    var globalHighPoint = highPoints.length > 0 ? highPoints.reduce(function(prev, current) {
                        return (prev.level > current.level) ? prev : current;
                    }) : null;

                    var globalLowPoint = lowPoints.length > 0 ? lowPoints.reduce(function(prev, current) {
                        return (prev.level < current.level) ? prev : current;
                    }) : null;

                    var timeSinceFullFlo = 'Ikke tilgjengelig';
                    var fullFloPoint = null;

                    if (catchTimestamp) {
                        var precedingHighs = highPoints.filter(function(p) {
                            return p.timestamp <= catchTimestamp;
                        });

                        if (precedingHighs.length > 0) {
                            fullFloPoint = precedingHighs.reduce(function(prev, current) {
                                return (prev.timestamp > current.timestamp) ? prev : current;
                            });
                        }
                    }

                    if (fullFloPoint && catchTimestamp) {
                        var diffMinutes = Math.round((catchTimestamp - fullFloPoint.timestamp) / (1000 * 60));
                        var diffHours = Math.floor(diffMinutes / 60);
                        var remainingMinutes = diffMinutes % 60;
                        timeSinceFullFlo = diffHours + ' timer og ' + remainingMinutes + ' minutter etter full flo';
                    }

                    var sections = [];
                    sections.push('<div><strong>Tid siden full flo:</strong> ' + timeSinceFullFlo + '</div>');
                    sections.push('<div><small>Stasjon: ' + stationName + (stationCode ? ' (' + stationCode + ')' : '') + '</small></div>');

                    if (globalHighPoint) {
                        var highLabel = globalHighPoint.raw && globalHighPoint.raw.timestamp
                            ? formatDateTime(globalHighPoint.raw.timestamp)
                            : formatTimeLabel(globalHighPoint.timestamp);
                        var highLevel = typeof globalHighPoint.level === 'number'
                            ? globalHighPoint.level.toFixed(2) + ' m'
                            : 'Ukjent';
                        sections.push('<div><small>Høyeste flo: ' + highLabel + ' (' + highLevel + ')</small></div>');
                    }

                    if (globalLowPoint) {
                        var lowLabel = globalLowPoint.raw && globalLowPoint.raw.timestamp
                            ? formatDateTime(globalLowPoint.raw.timestamp)
                            : formatTimeLabel(globalLowPoint.timestamp);
                        var lowLevel = typeof globalLowPoint.level === 'number'
                            ? globalLowPoint.level.toFixed(2) + ' m'
                            : 'Ukjent';
                        sections.push('<div><small>Laveste nivå: ' + lowLabel + ' (' + lowLevel + ')</small></div>');
                    }

                    $tideContent.html(sections.join(''));

                } else {
                    $tideContent.html('<p>Ingen tidevannsdata funnet for denne fangsten.</p>');
                }
            },
            error: function() {
                $tideContent.html('<p>Feil ved henting av tidevannsdata.</p>');
            }
        });
    }

    function loadModalWaterLevelData(catchId) {
        console.log('loadModalWaterLevelData called for catchId:', catchId);
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_water_level',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Successfully fetched water level data:', response.data);
                    updateModalWaterLevelDisplay(response.data, catchId);
                } else {
                    console.error('Error fetching water level data:', response.data);
                    var errorHtml = '<div class="water-level-section">' +
                        '<h4>🌊 Vannføring</h4>' +
                        '<p class="water-error">' + (response.data || 'Ukjent feil') + '</p>' +
                    '</div>';
                    $('.catch-details-full .water-level-section').replaceWith(errorHtml);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error fetching water level data:', textStatus, errorThrown);
                var errorHtml = '<div class="water-level-section">' +
                    '<h4>🌊 Vannføring</h4>' +
                    '<p class="water-error">AJAX-feil: ' + textStatus + '</p>' +
                '</div>';
                $('.catch-details-full .water-level-section').replaceWith(errorHtml);
            }
        });
    }
    
    /**
     * Update modal display with loaded water level data
     */
    function updateModalWaterLevelDisplay(waterLevel, catchId) {
        console.log('updateModalWaterLevelDisplay: Updating with water level data', waterLevel);
        
        var waterLevelHtml = '';
        
        if (waterLevel.error) {
            waterLevelHtml = '<div class="water-level-section">' +
                '<h4>🌊 Vannføring</h4>' +
                '<p class="water-error">' + waterLevel.error + '</p>' +
            '</div>';
        } else if (waterLevel.water_data && waterLevel.water_data.water_level !== undefined) {
            var storedTemperatureLine = '';
            
            var measurementStatus = waterLevel.water_data && waterLevel.water_data.measurement_status ? waterLevel.water_data.measurement_status : 'missing';
            var hasFreshMeasurement = measurementStatus === 'fresh';
            var staleInfo = waterLevel.water_data && waterLevel.water_data.stale_measurement ? waterLevel.water_data.stale_measurement : null;

            // Trend-farge og ikon basert på vannføring trend
            var trendColor = '#666';
            var trendIcon = '📊';

            if (hasFreshMeasurement) {
                switch(waterLevel.water_data.trend) {
                    case 'kraftig_voksende':
                        trendColor = '#059669'; // Mørk grønn
                        trendIcon = '📈⬆️';
                        break;
                    case 'stigende':
                        trendColor = '#10b981'; // Grønn
                        trendIcon = '📈';
                        break;
                    case 'ganske_stabil':
                        trendColor = '#6b7280'; // Grå
                        trendIcon = '📊';
                        break;
                    case 'helt_stabil':
                        trendColor = '#374151'; // Mørk grå
                        trendIcon = '➖';
                        break;
                    case 'synkende':
                        trendColor = '#ef4444'; // Rød
                        trendIcon = '📉';
                        break;

                    case 'kraftig_synkende':
                        trendColor = '#dc2626'; // Mørk rød
                        trendIcon = '📉⬇️';
                        break;
                    default:
                        trendColor = '#666';
                        trendIcon = '📊';
                }
            }

            var flowLine = '<span><strong>Vannføring:</strong> Ingen måling denne dato enda</span>';
            var temperatureLine = '<span><strong>Vanntemperatur:</strong> Ingen måling denne dato enda</span>';
            var staleDetailsLine = '';

            if (hasFreshMeasurement && waterLevel.water_data) {
                if (waterLevel.water_data.flow !== null && waterLevel.water_data.flow !== undefined && !isNaN(parseFloat(waterLevel.water_data.flow))) {
                    flowLine = '<span><strong>Vannføring:</strong> ' + parseFloat(waterLevel.water_data.flow).toFixed(1) + ' m³/s</span>';
                }

                var tempValue = waterLevel.water_data.temperature;
                if (tempValue !== null && tempValue !== undefined && !isNaN(parseFloat(tempValue))) {
                    var formattedTemp = parseFloat(tempValue).toFixed(1);
                    temperatureLine = '<span><strong>Vanntemperatur:</strong> ' + formattedTemp + ' °C</span>';
                }
            } else if (staleInfo) {
                var staleParts = [];
                if (staleInfo.flow !== null && staleInfo.flow !== undefined && !isNaN(parseFloat(staleInfo.flow))) {
                    staleParts.push('vannføring ' + parseFloat(staleInfo.flow).toFixed(1) + ' m³/s');
                }
                if (staleInfo.temperature !== null && staleInfo.temperature !== undefined && !isNaN(parseFloat(staleInfo.temperature))) {
                    staleParts.push('temperatur ' + parseFloat(staleInfo.temperature).toFixed(1) + ' °C');
                }
                var staleTimestampText = staleInfo.timestamp ? formatDateTime(staleInfo.timestamp) : null;
                if (staleParts.length) {
                    staleDetailsLine = '<small style="display:block;margin-top:4px;color:#6b7280;">Siste registrerte måling' +
                        (staleTimestampText ? ' (' + staleTimestampText + ')' : '') +
                        ': ' + staleParts.join(', ') + '</small>';
                }
            }

            if (staleDetailsLine) {
                temperatureLine += staleDetailsLine;
            }

            var measuredLine = (hasFreshMeasurement && waterLevel.water_data && waterLevel.water_data.timestamp)
                ? '<span><strong>Målt:</strong> ' + formatDateTime(waterLevel.water_data.timestamp) + '</span>'
                : '';

            var trendLine = '';
            if (hasFreshMeasurement && waterLevel.water_data && waterLevel.water_data.trend_description) {
                trendLine = '<span style="color: ' + trendColor + '; font-weight: bold;"><strong>' + trendIcon + '</strong> ' + waterLevel.water_data.trend_description + '</span>';
            } else if (!hasFreshMeasurement && waterLevel.water_data && waterLevel.water_data.trend_description) {
                trendLine = '<span style="color: #6b7280; font-weight: bold;"><strong>ℹ️</strong> ' + waterLevel.water_data.trend_description + '</span>';
            }

            waterLevelHtml = '<div class="water-level-section">' +
                '<h4>🌊 Vannføring</h4>' +
                '<div class="water-data">' +
                    '<span><strong>Stasjon:</strong> ' + (waterLevel.station_name || 'Ukjent') + '</span>' +
                    flowLine +
                    temperatureLine +
                    measuredLine +
                    trendLine +
                '</div>' +
                '<small>Kilde: ' + ((waterLevel.water_data && waterLevel.water_data.source) || 'Ukjent') + '</small>' +
                (waterLevel.water_data && waterLevel.water_data.note ? '<br><small style="color: #666;">' + waterLevel.water_data.note + '</small>' : '') +
                (waterLevel.description && waterLevel.description.includes('Manuelt valgt') ? 
                    '<br><small style="color: #10b981; font-weight: bold;">🎯 ' + waterLevel.description + '</small>' : '') +
                storedTemperatureLine +
            '</div>';
        } else if (waterLevel.beat_name) {
            waterLevelHtml = '<div class="water-level-section">' +
                '<h4>🌊 Vannføring</h4>' +
                '<p>Ingen måling denne dato enda for ' + (waterLevel.station_name || 'ukjent stasjon') + '</p>' +
            '</div>';
        }
        
        // Replace the water level section in modal with updated data if present
        if (waterLevelHtml) {
            var currentWaterSection = $('.catch-details-full .water-level-section');
            if (currentWaterSection.length) {
                currentWaterSection.replaceWith(waterLevelHtml);
            } else {
                // If no water section exists yet, insert before tidewater section
                var tidewaterSection = $('.catch-details-full #modal-tidewater-section');
                if (tidewaterSection.length) {
                    tidewaterSection.before(waterLevelHtml);
                } else {
                    // Otherwise insert after the last details-section
                    $('.catch-details-full .details-actions').before(waterLevelHtml);
                }
            }
        }
    }
    
    // Function to update catch list (called from form)
    window.updateCatchList = function() {
        location.reload();
    };
    
    // Function to filter by year
    window.filterByYear = function(year) {
        var container = $('.fiskedagbok-list-container');
        var originalContent = container.html();
        
        // Vis loading
        container.append('<div id="loading-year">Laster fangster...</div>');
        
        $.post(fiskedagbok_ajax.ajax_url, {
            action: 'filter_catches_by_year',
            nonce: fiskedagbok_ajax.nonce,
            year: year
        })
        .done(function(response) {
            if (response.success) {
                // Erstatt hele container-innholdet
                container.html('<h3>Mine fangster</h3>' + response.data.html);
                
                // Reinitialiser event handlers
                initializeCatchListEvents();
                
                // Sørg for at modaler finnes fortsatt
                if ($('#catch-details-modal').length === 0) {
                    $('body').append(`
                        <div id="catch-details-modal" class="modal catch-details-modal" style="display: none;">
                            <div class="modal-content large">
                                <span class="close">&times;</span>
                                <div id="catch-details-content">
                                    <!-- Fangst detaljer vil bli lagt til her -->
                                </div>
                            </div>
                        </div>
                    `);
                }
            } else {
                alert('Feil ved filtrering: ' + response.data);
            }
        })
        .fail(function() {
            alert('Teknisk feil oppstod ved filtrering.');
        })
        .always(function() {
            $('#loading-year').remove();
        });
    };
    
    // Funksjon for å initialisere event handlers på nytt
    function initializeCatchListEvents() {
        console.log('Initializing catch list events...');
        
        // View details
        $('.view-details-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            console.log('View details clicked for catch:', catchId);
            viewCatchDetails(catchId);
        });
        
        // Debug: View details with dummy data
        $('.view-details-debug-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            console.log('DEBUG: View details (test mode) clicked for catch:', catchId);
            viewCatchDetailsDebug(catchId);
        });
        
        // Edit catch
        $('.edit-catch-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            console.log('Edit clicked for catch:', catchId);
            loadCatchForEdit(catchId);
            $('#edit-catch-modal').show();
        });
        
        // Delete catch
        $('.delete-catch-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            console.log('Delete clicked for catch:', catchId);
            if (confirm('Er du sikker på at du vil slette denne fangsten?')) {
                deleteCatch(catchId);
            }
        });
        
        // Close modal events
        $('.close, .cancel-edit').off('click').on('click', function() {
            console.log('Modal close clicked');
            $('#edit-catch-modal').hide();
            $('#catch-details-modal').hide();
        });
        
        // Modal click outside to close
        $(window).off('click.modal').on('click.modal', function(event) {
            if (event.target.classList.contains('modal')) {
                console.log('Modal background clicked - closing modal');
                $('.modal').hide();
            }
        });
        
        console.log('Event handlers initialized. Found buttons:', {
            'view-details': $('.view-details-btn').length,
            'edit-catch': $('.edit-catch-btn').length,
            'delete-catch': $('.delete-catch-btn').length
        });
    }
    
    // Initialiser event handlers ved oppstart
    initializeCatchListEvents();
});
</script>
