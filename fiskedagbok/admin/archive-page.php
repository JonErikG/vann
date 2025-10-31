<?php
// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

// Håndter AJAX import
if (isset($_POST['import_csv_to_archive']) && !empty($_FILES['csv_file']['name'])) {
    // Dette håndteres via AJAX nå
}

// Hent statistikk fra arkivet
global $wpdb;
$archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
$total_archive = $wpdb->get_var("SELECT COUNT(*) FROM $archive_table");
$claimed_archive = $wpdb->get_var("SELECT COUNT(*) FROM $archive_table WHERE claimed = 1");
$unclaimed_archive = $total_archive - $claimed_archive;

// Hent noen nylige imports
$recent_imports = $wpdb->get_results(
    "SELECT fisher_name, COUNT(*) as count, MAX(imported_at) as last_import 
     FROM $archive_table 
     GROUP BY fisher_name 
     ORDER BY last_import DESC 
     LIMIT 10"
);
?>

<div class="wrap">
    <h1>CSV Arkiv</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_archive; ?></span>
            <span class="stat-label">Totalt i arkiv</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $claimed_archive; ?></span>
            <span class="stat-label">Clamet av brukere</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $unclaimed_archive; ?></span>
            <span class="stat-label">Tilgjengelig</span>
        </div>
    </div>
    
    <div class="card">
        <h2>Import CSV til Arkiv</h2>
        <p>Last opp CSV-filen for å importere alle fangster til arkivet. Fangster vil være tilgjengelige for søk og klaiming.</p>
        
        <form id="archive-import-form" enctype="multipart/form-data">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('fiskedagbok_admin_nonce'); ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row">CSV-fil</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <p class="description">Velg CSV-fil å importere til arkivet</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="import_csv_to_archive" class="button-primary" value="Import til Arkiv">
            </p>
        </form>
        
        <div id="import-progress" style="display: none;">
            <p>Importerer...</p>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
        
        <div id="import-result"></div>
    </div>
    
    <?php if (!empty($recent_imports)): ?>
    <div class="card">
        <h2>Nylige imports</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Fisher Name</th>
                    <th>Antall fangster</th>
                    <th>Sist importert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_imports as $import): ?>
                <tr>
                    <td><?php echo esc_html($import->fisher_name); ?></td>
                    <td><?php echo esc_html($import->count); ?></td>
                    <td><?php echo esc_html($import->last_import); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Instruksjoner</h2>
        <ol>
            <li><strong>Import CSV:</strong> Last opp hele CSV-filen til arkivet</li>
            <li><strong>Arkivering:</strong> Alle fangster lagres i arkiv-tabellen</li>
            <li><strong>Duplikatkontroll:</strong> Fangster med samme catch_id importeres kun én gang</li>
            <li><strong>Søk og klaim:</strong> Bruk "Søk og Klaim" siden for å tildele fangster til brukere</li>
            <li><strong>Brukersøk:</strong> Brukere kan selv søke og klaime sine fangster på frontend</li>
        </ol>
        
        <h3>Forskjell mellom Import CSV og CSV Arkiv</h3>
        <ul>
            <li><strong>Import CSV:</strong> Importerer direkte til brukerenes fiskedagbøker (krever bruker-matching)</li>
            <li><strong>CSV Arkiv:</strong> Lagrer alle fangster i arkiv for senere klaiming og søk</li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#archive-import-form').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'import_csv_to_archive');
        
        $('#import-progress').show();
        $('#import-result').empty();
        $(this).find('input[type="submit"]').prop('disabled', true).val('Importerer...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var html = '<div class="notice notice-success">' +
                        '<p><strong>Import fullført!</strong></p>' +
                        '<ul>' +
                            '<li>Importert: ' + result.imported + ' fangster</li>' +
                            '<li>Duplikater hoppet over: ' + result.duplicates + '</li>' +
                            '<li>Feil: ' + result.errors + '</li>' +
                            '<li>Totalt behandlet: ' + result.total + '</li>' +
                        '</ul>';
                    
                    if (result.error_details && result.error_details.length > 0) {
                        html += '<p><strong>Feildetaljer:</strong></p><ul>';
                        result.error_details.forEach(function(error) {
                            html += '<li>' + error + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                    $('#import-result').html(html);
                    
                    // Oppdater statistikk
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $('#import-result').html('<div class="notice notice-error"><p>Feil: ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Det oppstod en teknisk feil.';
                if (xhr.responseText) {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        if (errorData.data) {
                            errorMsg += ' Detaljer: ' + errorData.data;
                        }
                    } catch (e) {
                        errorMsg += ' Response: ' + xhr.responseText.substring(0, 200);
                    }
                }
                $('#import-result').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                console.error('AJAX Error:', xhr, status, error);
            },
            complete: function() {
                $('#import-progress').hide();
                $('#archive-import-form').find('input[type="submit"]').prop('disabled', false).val('Import til Arkiv');
            }
        });
    });
});
</script>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-number {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    animation: progress 2s ease-in-out infinite;
}

@keyframes progress {
    0% { width: 0%; }
    50% { width: 100%; }
    100% { width: 0%; }
}
</style>