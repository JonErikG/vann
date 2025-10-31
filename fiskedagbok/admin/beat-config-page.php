<?php
/**
 * Beat Vannstand Konfiguration Side
 */

// Forhindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Definerte vannstand stasjoner
$water_stations = array(
    'storsteinsholen' => array(
        'name' => 'Vannføring Storsteinshølen',
        'description' => 'For Elvadalen (Mellom Bjørset og Svorkmo kraftverk)'
    ),
    'rennebu_oppstroms' => array(
        'name' => 'Rennebu oppstrøms Grana',
        'description' => 'For Rennebu oppstrøms Grana området'
    ),
    'syrstad' => array(
        'name' => 'Vannføring Syrstad',
        'description' => 'For utløpet av Grana ned til Bjørset dammen'
    ),
    'nedstroms_svorkmo' => array(
        'name' => 'Nedstrøms Svorkmo kraftverk',
        'description' => 'For nedenfor Svorkmo kraftverk'
    ),
    'oppstroms_brattset' => array(
        'name' => 'Vannføring oppstrøms Brattset',
        'description' => 'For oppstrøms Brattset området'
    )
);

// Håndter form submission
if (isset($_POST['save_beat_config']) && check_admin_referer('beat_config_nonce', 'beat_config_nonce')) {
    $beat_config_table = $wpdb->prefix . 'fiskedagbok_beat_water_stations';
    
    // Slett alle eksisterende konfigurasjoner
    $wpdb->query("DELETE FROM $beat_config_table");
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($_POST['beat_config'] as $beat_name => $station_id) {
        if (!empty($station_id) && isset($water_stations[$station_id])) {
            $result = $wpdb->insert(
                $beat_config_table,
                array(
                    'beat_name' => sanitize_text_field($beat_name),
                    'water_station_id' => sanitize_text_field($station_id),
                    'water_station_name' => sanitize_text_field($water_stations[$station_id]['name']),
                    'description' => sanitize_text_field($water_stations[$station_id]['description'])
                )
            );
            
            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }
    
    if ($success_count > 0) {
        echo '<div class="notice notice-success"><p>✓ ' . $success_count . ' beat konfigurasjoner lagret!</p></div>';
    }
    if ($error_count > 0) {
        echo '<div class="notice notice-error"><p>✗ ' . $error_count . ' feil oppstod under lagring.</p></div>';
    }
}

// Hent alle unike beat navn fra databasen
$catches_table = $wpdb->prefix . 'fiskedagbok_catches';
$archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';

$beats_from_catches = $wpdb->get_col("
    SELECT DISTINCT beat_name 
    FROM $catches_table 
    WHERE beat_name IS NOT NULL AND beat_name != ''
    ORDER BY beat_name
");

$beats_from_archive = $wpdb->get_col("
    SELECT DISTINCT beat_name 
    FROM $archive_table 
    WHERE beat_name IS NOT NULL AND beat_name != ''
    ORDER BY beat_name
");

// Kombiner og fjern duplikater
$all_beats = array_unique(array_merge($beats_from_catches, $beats_from_archive));
sort($all_beats);

// Hent eksisterende konfigurasjoner
$beat_config_table = $wpdb->prefix . 'fiskedagbok_beat_water_stations';
$existing_configs = $wpdb->get_results("
    SELECT beat_name, water_station_id 
    FROM $beat_config_table
", OBJECT_K);

?>

<div class="wrap">
    <h1>🌊 Beat Vannstand Konfiguration</h1>
    <p>Koble hver beat/fiskeplass til riktig vannstand stasjon for å vise vannføring data på fangstene.</p>
    
    <?php if (empty($all_beats)): ?>
        <div class="notice notice-warning">
            <p>⚠️ Ingen beat navn funnet i databasen. Importer fangster først for å se tilgjengelige beat.</p>
        </div>
    <?php else: ?>
        
        <form method="post">
            <?php wp_nonce_field('beat_config_nonce', 'beat_config_nonce'); ?>
            
            <h2>Vannstand Stasjoner</h2>
            <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;">
                <?php foreach ($water_stations as $id => $station): ?>
                    <p><strong><?php echo esc_html($station['name']); ?></strong><br>
                    <em><?php echo esc_html($station['description']); ?></em></p>
                <?php endforeach; ?>
            </div>
            
            <h2>Beat Konfiguration</h2>
            <p>Velg hvilken vannstand stasjon hver beat skal kobles til:</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40%;">Beat Navn</th>
                        <th style="width: 50%;">Vannstand Stasjon</th>
                        <th style="width: 10%;">Antall Fangster</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_beats as $beat): ?>
                        <?php 
                        // Tell fangster for denne beat
                        $catch_count = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM (
                                SELECT 1 FROM $catches_table WHERE beat_name = %s
                                UNION ALL
                                SELECT 1 FROM $archive_table WHERE beat_name = %s
                            ) as combined
                        ", $beat, $beat));
                        
                        $current_station = isset($existing_configs[$beat]) ? $existing_configs[$beat]->water_station_id : '';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($beat); ?></strong></td>
                            <td>
                                <select name="beat_config[<?php echo esc_attr($beat); ?>]" class="regular-text">
                                    <option value="">-- Velg vannstand stasjon --</option>
                                    <?php foreach ($water_stations as $station_id => $station_info): ?>
                                        <option value="<?php echo esc_attr($station_id); ?>" 
                                                <?php selected($current_station, $station_id); ?>>
                                            <?php echo esc_html($station_info['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?php echo number_format($catch_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_beat_config" class="button-primary" 
                       value="💾 Lagre Beat Konfiguration" />
            </p>
        </form>
        
    <?php endif; ?>
    
    <h2>📊 Konfigurasjon Status</h2>
    <?php
    $beat_config_table = $wpdb->prefix . 'fiskedagbok_beat_water_stations';
    $configured_beats = $wpdb->get_results("
        SELECT bc.beat_name, bc.water_station_name, bc.description,
               COUNT(c1.id) + COUNT(c2.id) as total_catches
        FROM $beat_config_table bc
        LEFT JOIN $catches_table c1 ON c1.beat_name = bc.beat_name
        LEFT JOIN $archive_table c2 ON c2.beat_name = bc.beat_name
        GROUP BY bc.beat_name, bc.water_station_name, bc.description
        ORDER BY bc.beat_name
    ");
    
    if ($configured_beats): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Beat</th>
                    <th>Vannstand Stasjon</th>
                    <th>Beskrivelse</th>
                    <th>Fangster</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configured_beats as $config): ?>
                    <tr>
                        <td><strong><?php echo esc_html($config->beat_name); ?></strong></td>
                        <td><?php echo esc_html($config->water_station_name); ?></td>
                        <td><em><?php echo esc_html($config->description); ?></em></td>
                        <td><?php echo number_format($config->total_catches); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Ingen beat konfigurert ennå.</p>
    <?php endif; ?>
    
    <hr>
    <h2>🔗 Integrasjon Info</h2>
    <div style="background: #e7f3ff; padding: 15px; border-radius: 5px;">
        <p><strong>Orkla Water Level Plugin:</strong></p>
        <p>Denne konfigurasjonen kobler fiskedagbok beat til vannstand data fra Orkla Water Level pluginet.</p>
        <p>Når en fangst vises, vil systemet automatisk hente og vise vannføring data for datoen fangsten ble tatt.</p>
        
        <?php if (is_plugin_active('orkla-water-level/orkla-water-level.php')): ?>
            <p style="color: green;">✓ Orkla Water Level plugin er aktiv</p>
        <?php else: ?>
            <p style="color: red;">✗ Orkla Water Level plugin er ikke aktiv</p>
        <?php endif; ?>
    </div>
</div>

<style>
.wp-list-table th,
.wp-list-table td {
    padding: 12px 8px;
}

select.regular-text {
    width: 100%;
}
</style>