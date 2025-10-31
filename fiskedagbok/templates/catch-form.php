<div class="fiskedagbok-form-container">
    <h3>Registrer ny fangst</h3>
    
    <form id="fiskedagbok-form" class="fiskedagbok-form">
        <div class="form-row">
            <div class="form-group">
                <label for="date">Dato *</label>
                <input type="date" id="date" name="date" required>
            </div>
            <div class="form-group">
                <label for="time_of_day">Tid</label>
                <input type="time" id="time_of_day" name="time_of_day">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="river_name">Elv *</label>
                <input type="text" id="river_name" name="river_name" required placeholder="F.eks. Orkla">
            </div>
            <div class="form-group">
                <label for="beat_name">Beat/Strekning</label>
                <input type="text" id="beat_name" name="beat_name" placeholder="F.eks. Øyum Felles">
            </div>
        </div>
        
        <div class="form-group">
            <label for="fishing_spot">Fiskeplass</label>
            <input type="text" id="fishing_spot" name="fishing_spot" placeholder="Spesifikt sted">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="fish_type">Fisketype *</label>
                <select id="fish_type" name="fish_type" required>
                    <option value="">Velg fisketype</option>
                    <option value="Laks">Laks</option>
                    <option value="Sjøørret">Sjøørret</option>
                </select>
            </div>
            <div class="form-group">
                <label for="equipment">Utstyr</label>
                <select id="equipment" name="equipment">
                    <option value="">Velg utstyr</option>
                    <option value="Flue">Flue</option>
                    <option value="Mark">Mark</option>
                    <option value="Sluk">Sluk</option>
                    <option value="Spinner">Spinner</option>
                    <option value="Jig">Jig</option>
                    <option value="Annet">Annet</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="fly_lure">Flue/Sluk</label>
            <input type="text" id="fly_lure" name="fly_lure" placeholder="Hvilken flue eller sluk ble brukt">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="weight_kg">Vekt (kg)</label>
                <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="0" placeholder="0.0">
            </div>
            <div class="form-group">
                <label for="length_cm">Lengde (cm)</label>
                <input type="number" id="length_cm" name="length_cm" step="0.1" min="0" placeholder="0.0">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="sex">Kjønn</label>
                <select id="sex" name="sex">
                    <option value="unknown">Ukjent</option>
                    <option value="male">Hann</option>
                    <option value="female">Hunn</option>
                </select>
            </div>
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="released" name="released" value="1">
                    Sluppet tilbake
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Notater</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Ekstra informasjon om fangsten..."></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="submit-button">Registrer fangst</button>
        </div>
        
        <div id="form-message" class="form-message"></div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Sett dagens dato som standard
    $('#date').val(new Date().toISOString().split('T')[0]);
    
    // Beregn ukenummer automatisk når dato endres
    $('#date').change(function() {
        var date = new Date($(this).val());
        var week = getWeekNumber(date);
        $('#week').val(week);
    });
    
    // Trigger initial week calculation
    $('#date').trigger('change');
    
    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
        return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
    }

    $('#fiskedagbok-form').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=submit_catch&nonce=' + fiskedagbok_ajax.nonce;
        
        // Vis lasting
        $('#form-message').html('<p class="loading">Lagrer fangst...</p>');
        $('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: fiskedagbok_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#form-message').html('<p class="success">' + response.data + '</p>');
                    $('#fiskedagbok-form')[0].reset();
                    $('#date').val(new Date().toISOString().split('T')[0]);
                    
                    // Oppdater fangstliste hvis den finnes på siden
                    if (typeof updateCatchList === 'function') {
                        updateCatchList();
                    }
                } else {
                    $('#form-message').html('<p class="error">Feil: ' + response.data + '</p>');
                }
            },
            error: function() {
                $('#form-message').html('<p class="error">Det oppstod en feil ved lagring.</p>');
            },
            complete: function() {
                $('button[type="submit"]').prop('disabled', false);
            }
        });
    });
});
</script>