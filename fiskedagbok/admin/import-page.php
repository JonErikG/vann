<?php
// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

// Håndter CSV import
$import_message = '';
if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['name'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'import_csv')) {
        $import_result = handle_csv_import($_FILES['csv_file']);
        $import_message = $import_result;
    }
}

function handle_csv_import($file) {
    global $wpdb;
    
    // Valider filtype
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($file_ext !== 'csv') {
        return '<div class="notice notice-error"><p>Kun CSV-filer er tillatt.</p></div>';
    }
    
    // Åpne og les CSV-fil
    $csv_data = array_map('str_getcsv', file($file['tmp_name']));
    $header = array_shift($csv_data); // Fjern header rad
    
    $table_name = $wpdb->prefix . 'fiskedagbok_catches';
    $imported = 0;
    $errors = 0;
    $user_mappings = array();
    
    foreach ($csv_data as $row) {
        if (count($row) !== count($header)) {
            $errors++;
            continue;
        }
        
        $data = array_combine($header, $row);
        
        // Finn eller opprett bruker basert på fisher_name
        $user_id = null;
        $fisher_name = sanitize_text_field($data['fisher_name']);
        
        if (!empty($fisher_name)) {
            // Sjekk om vi allerede har mappet denne brukeren
            if (isset($user_mappings[$fisher_name])) {
                $user_id = $user_mappings[$fisher_name];
            } else {
                // Prøv å finne eksisterende bruker
                $user = get_user_by('display_name', $fisher_name);
                if (!$user) {
                    $user = get_user_by('login', $fisher_name);
                }
                if (!$user) {
                    // Søk i first_name og last_name
                    $users = get_users(array(
                        'search' => '*' . $fisher_name . '*',
                        'search_columns' => array('display_name', 'user_login', 'user_email')
                    ));
                    if (!empty($users)) {
                        $user = $users[0];
                    }
                }
                
                if ($user) {
                    $user_id = $user->ID;
                    $user_mappings[$fisher_name] = $user_id;
                } else {
                    // Opprett ny bruker hvis ønskelig (eller sett til null for admin review)
                    $user_mappings[$fisher_name] = null;
                }
            }
        }
        
        // Forbered data for innsetting
        $catch_data = array(
            'user_id' => $user_id,
            'catch_id' => sanitize_text_field($data['catch_id']),
            'date' => sanitize_text_field($data['date']),
            'time_of_day' => sanitize_text_field($data['time_of_day']),
            'week' => intval($data['week']),
            'river_id' => intval($data['river_id']),
            'river_name' => sanitize_text_field($data['river_name']),
            'beat_id' => intval($data['beat_id']),
            'beat_name' => sanitize_text_field($data['beat_name']),
            'fishing_spot' => sanitize_text_field($data['fishing_spot']),
            'fish_type' => sanitize_text_field($data['fish_type']),
            'equipment' => sanitize_text_field($data['equipment']),
            'weight_kg' => !empty($data['weight_kg']) ? floatval($data['weight_kg']) : null,
            'length_cm' => !empty($data['length_cm']) ? floatval($data['length_cm']) : null,
            'released' => ($data['released'] === 'True' || $data['released'] === '1') ? 1 : 0,
            'sex' => sanitize_text_field($data['sex']),
            'boat' => sanitize_text_field($data['boat']),
            'fisher_name' => $fisher_name,
            'created_at' => sanitize_text_field($data['created_at']),
            'updated_at' => sanitize_text_field($data['updated_at']),
            'platform_reported_from' => sanitize_text_field($data['platform_reported_from'])
        );
        
        // Sett inn i database
        $result = $wpdb->insert($table_name, $catch_data);
        
        if ($result) {
            $imported++;
        } else {
            $errors++;
        }
    }
    
    $message = "<div class='notice notice-success'><p>Import fullført! $imported fangster importert.";
    if ($errors > 0) {
        $message .= " $errors feil oppstod.";
    }
    $message .= "</p></div>";
    
    // Vis bruker mappings
    if (!empty($user_mappings)) {
        $message .= "<div class='notice notice-info'><p><strong>Bruker mappings:</strong><br>";
        foreach ($user_mappings as $name => $user_id) {
            if ($user_id) {
                $user = get_user_by('ID', $user_id);
                $message .= "$name → {$user->display_name} (ID: $user_id)<br>";
            } else {
                $message .= "$name → Ingen bruker funnet<br>";
            }
        }
        $message .= "</p></div>";
    }
    
    return $message;
}
?>

<div class="wrap">
    <h1>Import CSV Data</h1>
    
    <?php echo $import_message; ?>
    
    <div class="card">
        <h2>Last opp CSV-fil</h2>
        <p>Last opp en CSV-fil med fangstdata. Filen må ha samme struktur som eksempel-filen.</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_csv'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">CSV-fil</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <p class="description">Velg CSV-fil å importere</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="import_csv" class="button-primary" value="Import CSV">
            </p>
        </form>
    </div>
    
    <div class="card">
        <h2>CSV Format</h2>
        <p>CSV-filen må inneholde følgende kolonner:</p>
        <ul>
            <li><strong>catch_id</strong> - Unik ID for fangst (valgfri)</li>
            <li><strong>date</strong> - Dato (YYYY-MM-DD)</li>
            <li><strong>time_of_day</strong> - Tid (HH:MM)</li>
            <li><strong>week</strong> - Ukenummer</li>
            <li><strong>river_id</strong> - Elv ID (valgfri)</li>
            <li><strong>river_name</strong> - Elvenavn</li>
            <li><strong>beat_id</strong> - Beat ID (valgfri)</li>
            <li><strong>beat_name</strong> - Beat navn</li>
            <li><strong>fishing_spot</strong> - Fiskeplass</li>
            <li><strong>fish_type</strong> - Fisketype</li>
            <li><strong>equipment</strong> - Utstyr</li>
            <li><strong>weight_kg</strong> - Vekt i kg</li>
            <li><strong>length_cm</strong> - Lengde i cm</li>
            <li><strong>released</strong> - Sluppet (True/False)</li>
            <li><strong>sex</strong> - Kjønn (male/female/unknown)</li>
            <li><strong>boat</strong> - Båt</li>
            <li><strong>fisher_name</strong> - Fiskerens navn</li>
            <li><strong>created_at</strong> - Opprettet dato</li>
            <li><strong>updated_at</strong> - Oppdatert dato</li>
            <li><strong>platform_reported_from</strong> - Plattform</li>
        </ul>
        
        <h3>Bruker Mapping</h3>
        <p>Systemet vil prøve å koble fangster til eksisterende WordPress-brukere basert på <code>fisher_name</code> feltet. 
        Hvis ingen bruker finnes med det navnet, vil fangsten bli importert uten tilknytning til en bruker.</p>
    </div>
</div>