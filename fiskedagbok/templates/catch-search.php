<div class="fiskedagbok-search-container">
    <h3>Søk etter dine fangster</h3>
    
    <div class="search-form">
        <p>Søk etter fangster registrert med ditt navn for å legge dem til i din fiskedagbok.</p>
        
        <div class="form-group">
            <label for="search-name">Søk på navn:</label>
            <input type="text" id="search-name" placeholder="Skriv inn ditt fornavn og/eller etternavn">
            <button type="button" id="search-catches-btn" class="search-button">Søk fangster</button>
        </div>
        
        <div id="search-message" class="search-message"></div>
    </div>
    
    <div id="search-results" class="search-results" style="display: none;">
        <h4>Funnede fangster</h4>
        <p class="results-info">Trykk på en fangst for å se detaljer, eller bruk "Legg til i min dagbok" for å gjøre den til din.</p>
        
        <div id="catches-grid" class="catches-grid">
            <!-- Søkeresultater vil bli lagt til her -->
        </div>
    </div>
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
    var searchTimeout;
    
    // Søk når bruker skriver
    $('#search-name').on('input', function() {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val().trim();
        
        if (searchTerm.length >= 2) {
            searchTimeout = setTimeout(function() {
                searchCatches(searchTerm);
            }, 500);
        } else {
            $('#search-results').hide();
        }
    });
    
    // Søk knapp
    $('#search-catches-btn').click(function() {
        var searchTerm = $('#search-name').val().trim();
        if (searchTerm.length >= 2) {
            searchCatches(searchTerm);
        } else {
            alert('Skriv inn minst 2 tegn for å søke');
        }
    });
    
    function searchCatches(searchName) {
        $('#search-message').html('<p class="loading">Søker etter fangster...</p>');
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_catches_by_name',
                search_name: searchName,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                    $('#search-message').html('<p class="success">Fant ' + response.data.length + ' fangster</p>');
                } else {
                    $('#search-results').hide();
                    $('#search-message').html('<p class="error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#search-results').hide();
                $('#search-message').html('<p class="error">Feil ved søking</p>');
            }
        });
    }
    
    function displaySearchResults(catches) {
        var grid = $('#catches-grid');
        grid.empty();
        
        catches.forEach(function(catch_data) {
            var catchCard = createCatchCard(catch_data);
            grid.append(catchCard);
        });
        
        $('#search-results').show();
        
        // Bind event handlers for nye elementer
        bindCatchCardEvents();
    }
    
    function createCatchCard(catch_data) {
        var releasedText = catch_data.released == 1 ? 'Sluppet' : 'Tatt med';
        var releasedClass = catch_data.released == 1 ? 'released' : 'kept';
        
        var weatherIcon = '';
        if (catch_data.weather_data) {
            weatherIcon = '<span class="weather-icon" title="Værdata tilgjengelig">🌤️</span>';
        }
        
        return $('<div class="catch-card" data-catch-id="' + catch_data.id + '">' +
            '<div class="catch-card-header">' +
                '<span class="catch-date">' + formatDate(catch_data.date) + '</span>' +
                '<span class="catch-fish-type">' + catch_data.fish_type + '</span>' +
                weatherIcon +
            '</div>' +
            '<div class="catch-card-body">' +
                '<div class="catch-location">' +
                    '<strong>' + (catch_data.river_name || 'Ukjent elv') + '</strong>' +
                    (catch_data.fishing_spot ? ' - ' + catch_data.fishing_spot : '') +
                '</div>' +
                '<div class="catch-specs">' +
                    (catch_data.weight_kg ? '<span>Vekt: ' + catch_data.weight_kg + ' kg</span>' : '') +
                    (catch_data.length_cm ? '<span>Lengde: ' + catch_data.length_cm + ' cm</span>' : '') +
                '</div>' +
                '<div class="catch-meta">' +
                    '<span class="' + releasedClass + '">' + releasedText + '</span>' +
                    (catch_data.equipment ? '<span>Utstyr: ' + catch_data.equipment + '</span>' : '') +
                '</div>' +
                '<div class="fisher-info">' +
                    '<small>Registrert av: ' + catch_data.fisher_name + '</small>' +
                '</div>' +
            '</div>' +
            '<div class="catch-card-actions">' +
                '<button class="view-details-btn" data-catch-id="' + catch_data.id + '">Se detaljer</button>' +
                '<button class="claim-catch-btn" data-catch-id="' + catch_data.id + '">Legg til i min dagbok</button>' +
            '</div>' +
        '</div>');
    }
    
    function bindCatchCardEvents() {
        // Vis detaljer
        $('.view-details-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            viewCatchDetails(catchId);
        });
        
        // Klaim fangst
        $('.claim-catch-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            var button = $(this);
            
            if (confirm('Er du sikker på at dette er din fangst? Den vil bli lagt til i din fiskedagbok.')) {
                claimCatch(catchId, button);
            }
        });
        
        // Kort trykk for detaljer
        $('.catch-card').off('click').on('click', function(e) {
            if (!$(e.target).hasClass('claim-catch-btn') && !$(e.target).hasClass('view-details-btn')) {
                var catchId = $(this).data('catch-id');
                viewCatchDetails(catchId);
            }
        });
    }
    
    function claimCatch(catchId, button) {
        var originalText = button.text();
        button.text('Legger til...').prop('disabled', true);
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'claim_catch',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.closest('.catch-card').fadeOut(500, function() {
                        $(this).remove();
                    });
                    alert('Fangst lagt til i din fiskedagbok!');
                    
                    // Oppdater fangstliste hvis den finnes
                    if (typeof updateCatchList === 'function') {
                        updateCatchList();
                    }
                } else {
                    alert('Feil: ' + response.data);
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Det oppstod en feil');
                button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    function viewCatchDetails(catchId) {
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_details',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayCatchDetails(response.data);
                    $('#catch-details-modal').show();
                } else {
                    alert('Kunne ikke hente fangstdetaljer: ' + response.data);
                }
            },
            error: function() {
                alert('Feil ved henting av detaljer');
            }
        });
    }
    
    function displayCatchDetails(catch_data) {
        var weather = catch_data.weather;
        var weatherHtml = '';
        
        if (weather) {
            weatherHtml = '<div class="weather-section">' +
                '<h4>🌤️ Værforhold ' + formatDate(catch_data.date) + '</h4>' +
                '<div class="weather-details">' +
                    (weather.temperature !== null ? '<span>Temperatur: ' + weather.temperature + '°C</span>' : '') +
                    (weather.precipitation !== null ? '<span>Nedbør: ' + weather.precipitation + ' mm</span>' : '') +
                    (weather.wind_speed !== null ? '<span>Vind: ' + weather.wind_speed + ' m/s</span>' : '') +
                    (weather.wind_direction !== null ? '<span>Vindretning: ' + weather.wind_direction + '°</span>' : '') +
                '</div>' +
                '<small>Kilde: ' + (weather.source || 'Ukjent') + ' (' + (weather.station || 'Ukjent stasjon') + ')</small>' +
            '</div>';
        } else {
            weatherHtml = '<div class="weather-section">' +
                '<h4>🌤️ Værforhold</h4>' +
                '<p>Værdata ikke tilgjengelig</p>' +
                '<button class="fetch-weather-btn" data-catch-id="' + catch_data.id + '" data-date="' + catch_data.date + '">Hent værdata</button>' +
            '</div>';
        }
        
        var detailsHtml = '<div class="catch-details-full">' +
            '<h3>' + catch_data.fish_type + ' - ' + formatDate(catch_data.date) + '</h3>' +
            
            '<div class="details-section">' +
                '<h4>📍 Sted og tid</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Elv:</strong> ' + (catch_data.river_name || 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Beat:</strong> ' + (catch_data.beat_name || 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Fiskeplass:</strong> ' + (catch_data.fishing_spot || 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Tid:</strong> ' + (catch_data.time_of_day || 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Uke:</strong> ' + (catch_data.week || 'Ikke oppgitt') + '</span>' +
                '</div>' +
            '</div>' +
            
            '<div class="details-section">' +
                '<h4>🐟 Fiskdetaljer</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Type:</strong> ' + catch_data.fish_type + '</span>' +
                    '<span><strong>Vekt:</strong> ' + (catch_data.weight_kg ? catch_data.weight_kg + ' kg' : 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Lengde:</strong> ' + (catch_data.length_cm ? catch_data.length_cm + ' cm' : 'Ikke oppgitt') + '</span>' +
                    '<span><strong>Kjønn:</strong> ' + (catch_data.sex === 'male' ? 'Hann' : catch_data.sex === 'female' ? 'Hunn' : 'Ukjent') + '</span>' +
                    '<span><strong>Status:</strong> ' + (catch_data.released == 1 ? 'Sluppet tilbake' : 'Tatt med') + '</span>' +
                '</div>' +
            '</div>' +
            
            '<div class="details-section">' +
                '<h4>🎣 Utstyr og metode</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Utstyr:</strong> ' + (catch_data.equipment || 'Ikke oppgitt') + '</span>' +
                '</div>' +
            '</div>' +
            
            weatherHtml +
            
            '<div class="details-section tidewater-section">' +
                '<h4>🌊 Tidevannsdata</h4>' +
                '<div id="tidewater-loading" style="display: none;"><p>Laster tidevannsdata...</p></div>' +
                '<div id="tidewater-content"></div>' +
            '</div>' +
            
            (catch_data.notes ? 
                '<div class="details-section">' +
                    '<h4>📝 Notater</h4>' +
                    '<p>' + catch_data.notes + '</p>' +
                '</div>' : '') +
            
            '<div class="details-section">' +
                '<h4>ℹ️ Registreringsinformasjon</h4>' +
                '<div class="details-grid">' +
                    '<span><strong>Registrert av:</strong> ' + catch_data.fisher_name + '</span>' +
                    '<span><strong>Opprettet:</strong> ' + (catch_data.created_at ? formatDateTime(catch_data.created_at) : 'Ukjent') + '</span>' +
                    '<span><strong>Plattform:</strong> ' + (catch_data.platform_reported_from || 'Ukjent') + '</span>' +
                '</div>' +
            '</div>' +
            
            '<div class="details-actions">' +
                '<button class="claim-catch-btn" data-catch-id="' + catch_data.id + '">Legg til i min dagbok</button>' +
                '<button class="close-details-btn">Lukk</button>' +
            '</div>' +
        '</div>';
        
        $('#catch-details-content').html(detailsHtml);
        
        // Bind events for modal
        $('.close-details-btn, .close').click(function() {
            $('#catch-details-modal').hide();
        });
        
        $('.fetch-weather-btn').click(function() {
            var catchId = $(this).data('catch-id');
            var date = $(this).data('date');
            fetchWeatherData(catchId, date);
        });

        // Load tidewater data
        loadCatchTidewaterData(catch_data.id);
    }

    function loadCatchTidewaterData(catchId) {
        $('#tidewater-loading').show();
        $('#tidewater-content').empty();

        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_tidewater',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                $('#tidewater-loading').hide();
                
                if (response.success) {
                    var data = response.data;
                    var html = '<strong>' + data.station_name + '</strong> (' + data.count + ' datapunkter)<br><br>';
                    
                    // Grupper data etter time
                    var byHour = {};
                    data.data_points.forEach(function(point) {
                        var hour = point.timestamp.substr(11, 2);
                        if (!byHour[hour]) byHour[hour] = [];
                        byHour[hour].push(point);
                    });

                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';
                    
                    Object.keys(byHour).sort().forEach(function(hour) {
                        var points = byHour[hour];
                        var levels = points.map(p => parseFloat(p.water_level));
                        var minLevel = Math.min.apply(null, levels);
                        var maxLevel = Math.max.apply(null, levels);
                        var avgLevel = (levels.reduce((a, b) => a + b, 0) / levels.length).toFixed(2);

                        html += '<div style="padding: 8px; background-color: #f0f7ff; border-left: 4px solid #0073aa; border-radius: 4px;">' +
                                '<strong>' + hour + ':00</strong><br>' +
                                'Min: ' + minLevel.toFixed(2) + ' m<br>' +
                                'Maks: ' + maxLevel.toFixed(2) + ' m<br>' +
                                'Gj.snitt: ' + avgLevel + ' m' +
                                '</div>';
                    });
                    
                    html += '</div>';
                    $('#tidewater-content').html(html);
                } else {
                    $('#tidewater-content').html('<p style="color: #666;">Tidevannsdata ikke tilgjengelig ennå. Data hentes automatisk.</p>');
                }
            },
            error: function() {
                $('#tidewater-loading').hide();
                $('#tidewater-content').html('<p style="color: #d63638;">Feil ved henting av tidevannsdata</p>');
            }
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
                    // Reload detaljer for å vise værdata
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
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('no-NO');
    }
    
    function formatDateTime(dateTimeString) {
        var date = new Date(dateTimeString);
        return date.toLocaleDateString('no-NO') + ' ' + date.toLocaleTimeString('no-NO', {hour: '2-digit', minute: '2-digit'});
    }
    
    // Lukk modal ved klikk utenfor
    $(window).click(function(event) {
        if (event.target.id === 'catch-details-modal') {
            $('#catch-details-modal').hide();
        }
    });
});
</script>