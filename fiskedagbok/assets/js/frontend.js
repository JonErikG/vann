// Fiskedagbok Frontend JavaScript

jQuery(document).ready(function($) {
    
    // Auto-calculate week number when date changes
    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    
    // Set current date and calculate week
    function initializeForm() {
        var today = new Date().toISOString().split('T')[0];
        $('#date').val(today);
        
        var currentDate = new Date();
        var week = getWeekNumber(currentDate);
        if ($('#week').length) {
            $('#week').val(week);
        }
    }
    
    // Initialize form on page load
    initializeForm();
    
    // Update week when date changes
    $(document).on('change', '#date', function() {
        var date = new Date($(this).val());
        var week = getWeekNumber(date);
        if ($('#week').length) {
            $('#week').val(week);
        }
    });
    
    // Form validation
    function validateForm(formData) {
        var errors = [];
        
        if (!formData.get('date')) {
            errors.push('Dato er påkrevet');
        }
        
        if (!formData.get('fish_type')) {
            errors.push('Fisketype er påkrevet');
        }
        
        if (!formData.get('river_name')) {
            errors.push('Elv er påkrevet');
        }
        
        var weight = parseFloat(formData.get('weight_kg'));
        if (weight && weight < 0) {
            errors.push('Vekt kan ikke være negativ');
        }
        
        var length = parseFloat(formData.get('length_cm'));
        if (length && length < 0) {
            errors.push('Lengde kan ikke være negativ');
        }
        
        return errors;
    }
    
    // Handle form submission
    $(document).on('submit', '#fiskedagbok-form', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(this);
        
        // Validate form
        var errors = validateForm(formData);
        if (errors.length > 0) {
            $('#form-message').html('<p class="error">Feil: ' + errors.join(', ') + '</p>');
            return;
        }
        
        // Prepare data for AJAX
        var data = {};
        formData.forEach(function(value, key) {
            data[key] = value;
        });
        data.action = 'submit_catch';
        data.nonce = fiskedagbok_ajax.nonce;
        
        // Show loading state
        $('#form-message').html('<p class="loading">Lagrer fangst...</p>');
        form.find('button[type="submit"]').prop('disabled', true);
        
        // Submit via AJAX
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#form-message').html('<p class="success">' + response.data + '</p>');
                    
                    // Reset form
                    form[0].reset();
                    initializeForm();
                    
                    // Refresh catch list if it exists
                    if (typeof refreshCatchList === 'function') {
                        refreshCatchList();
                    } else if ($('#catches-list').length) {
                        // Reload page to show new catch
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    $('#form-message').html('<p class="error">Feil: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#form-message').html('<p class="error">Det oppstod en teknisk feil. Prøv igjen senere.</p>');
                console.error('AJAX Error:', error);
            },
            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });
    
    // Auto-hide success messages
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('success')) {
            setTimeout(function() {
                $(e.target).fadeOut();
            }, 3000);
        }
    });
    
    // Enhance dropdowns with common values
    var commonRivers = ['Orkla', 'Gaula', 'Nea', 'Stjørdalselva'];
    var commonEquipment = ['Flue', 'Mark', 'Sluk', 'Spinner', 'Jig'];
    var commonFishTypes = ['Laks', 'Sjøørret', 'Ørret', 'Røye'];
    
    // Add autocomplete functionality
    function addAutocomplete(selector, options) {
        $(selector).on('input', function() {
            var value = $(this).val().toLowerCase();
            var matches = options.filter(function(option) {
                return option.toLowerCase().indexOf(value) === 0;
            });
            
            // Simple autocomplete implementation
            if (matches.length > 0 && value.length > 0) {
                var firstMatch = matches[0];
                if (firstMatch.toLowerCase() !== value) {
                    var input = this;
                    setTimeout(function() {
                        input.value = firstMatch;
                        input.setSelectionRange(value.length, firstMatch.length);
                    }, 0);
                }
            }
        });
    }
    
    // Initialize autocomplete
    addAutocomplete('#river_name', commonRivers);
    
    // Load tidewater info for each catch in the list
    function loadTidewaterInfo() {
        console.log('loadTidewaterInfo: DISABLED - causing page hang');
        // TODO: Fix performance issue in get_tide_hours AJAX handler
        return;
    }
    
    // Load tidewater info when page is ready - DISABLED FOR NOW
    console.log('loadTidewaterInfo disabled until performance issue is fixed');
    
    // Load water level data for modal
    function loadModalWaterLevelData(catchId) {
        console.log('Loading water level data for catch:', catchId);
        
        var $waterSection = $('.water-level-section');
        if ($waterSection.length === 0) {
            console.warn('Water level section not found in modal');
            return;
        }
        
        $waterSection.html('<h4>🌊 Vannføring</h4><p>Laster vannføringsdata...</p>');
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_water_level',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                console.log('Water level response:', response);
                
                if (response.success && response.data) {
                    var data = response.data;
                    var html = '<h4>🌊 Vannføring</h4>';
                    
                    if (data.water_level !== null && data.water_level !== undefined) {
                        html += '<div class="water-level-details">';
                        html += '<p><strong>Vannstand:</strong> ' + data.water_level + ' cm</p>';
                        
                        if (data.temperature !== null && data.temperature !== undefined) {
                            html += '<p><strong>Vanntemperatur:</strong> ' + data.temperature + ' °C</p>';
                        }
                        
                        if (data.station_name) {
                            html += '<p><strong>Stasjon:</strong> ' + data.station_name + '</p>';
                        }
                        
                        if (data.timestamp) {
                            html += '<p><strong>Målt:</strong> ' + data.timestamp + '</p>';
                        }
                        
                        html += '</div>';
                    } else {
                        html += '<p>Ingen vannføringsdata tilgjengelig for denne fangsten.</p>';
                    }
                    
                    $waterSection.html(html);
                } else {
                    var errorMsg = response.data || 'Ukjent feil';
                    $waterSection.html('<h4>🌊 Vannføring</h4><p style="color: #d63638;">Feil ved henting av vannføringsdata: ' + errorMsg + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error loading water level data:', textStatus, errorThrown);
                $waterSection.html('<h4>🌊 Vannføring</h4><p style="color: #d63638;">Feil ved henting av vannføringsdata: ' + textStatus + '</p>');
            }
        });
    }
    
    // Load tidewater data for modal
    function loadModalTidewaterData(catchId, dateTimeIso) {
        console.log('Loading tidewater data for catch:', catchId, 'datetime:', dateTimeIso);
        
        var $tideSection = $('#modal-tidewater-section');
        var $tideContent = $('#modal-tidewater-content');
        
        if ($tideContent.length === 0) {
            console.warn('Tidewater content area not found in modal');
            return;
        }
        
        $tideContent.html('<p>Leter etter nærmeste tidevannsdata...</p>');
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_catch_tidewater',
                catch_id: catchId,
                nonce: fiskedagbok_ajax.nonce
            },
            success: function(response) {
                console.log('Tidewater response:', response);
                
                if (response.success && response.data) {
                    var data = response.data;
                    var html = '';
                    
                    if (data.water_level !== null && data.water_level !== undefined) {
                        html += '<div class="tidewater-details">';
                        html += '<p><strong>Vannstand:</strong> ' + data.water_level + ' cm</p>';
                        
                        if (data.station_name) {
                            html += '<p><strong>Stasjon:</strong> ' + data.station_name + '</p>';
                        }
                        
                        if (data.station_code) {
                            html += '<p><strong>Stasjonskode:</strong> ' + data.station_code + '</p>';
                        }
                        
                        if (data.timestamp) {
                            html += '<p><strong>Målt:</strong> ' + data.timestamp + '</p>';
                        }
                        
                        if (data.is_prediction) {
                            html += '<p><em>Dette er predikerte data</em></p>';
                        }
                        
                        html += '</div>';
                    } else {
                        html += '<p>Ingen tidevannsdata tilgjengelig for denne fangsten.</p>';
                    }
                    
                    $tideContent.html(html);
                } else {
                    var errorMsg = response.data || 'Ukjent feil';
                    $tideContent.html('<p style="color: #d63638;">Feil ved henting av tidevannsdata: ' + errorMsg + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error loading tidewater data:', textStatus, errorThrown);
                $tideContent.html('<p style="color: #d63638;">Feil ved henting av tidevannsdata: ' + textStatus + '</p>');
            }
        });
    }
    
    // Utility functions for external use
    window.fiskedagbokUtils = {
        getWeekNumber: getWeekNumber,
        validateForm: validateForm,
        initializeForm: initializeForm,
        loadTidewaterInfo: loadTidewaterInfo,
        loadModalWaterLevelData: loadModalWaterLevelData,
        loadModalTidewaterData: loadModalTidewaterData
    };
});