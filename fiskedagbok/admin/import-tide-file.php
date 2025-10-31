<?php
if (!defined('ABSPATH')) exit;

function fiskedagbok_admin_import_tide_file_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fiskedagbok_tidewater_data';
    $msg = '';

    if (isset($_POST['import_tide_file']) && !empty($_FILES['tide_file']['tmp_name'])) {
        $file = $_FILES['tide_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle) {
            $data_points = [];
            // First, read all data points into an array
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, '#') === 0 || trim($line) === '') continue;
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) < 2) continue;
                
                $timestamp = $parts[0];
                $water_level = (float)str_replace(',', '.', $parts[1]);
                $dt = date_create($timestamp);
                if (!$dt) continue;

                $data_points[] = [
                    'timestamp' => $dt,
                    'water_level' => $water_level
                ];
            }
            fclose($handle);

            $imported = 0;
            // Now, process the array to determine roles
            for ($i = 0; $i < count($data_points); $i++) {
                $current = $data_points[$i];
                $prev = ($i > 0) ? $data_points[$i - 1] : null;
                $next = ($i < count($data_points) - 1) ? $data_points[$i + 1] : null;
                
                $role = null;
                if ($prev && $next) {
                    if ($current['water_level'] > $prev['water_level'] && $current['water_level'] > $next['water_level']) {
                        $role = 'high';
                    } elseif ($current['water_level'] < $prev['water_level'] && $current['water_level'] < $next['water_level']) {
                        $role = 'low';
                    }
                }

                $station_code = 'TRD';
                if ($role) {
                    $station_code .= '|role:' . $role;
                }

                $wpdb->insert($table_name, array(
                    'timestamp' => $current['timestamp']->format('Y-m-d H:i:s'),
                    'water_level' => $current['water_level'],
                    'station_name' => 'Trondheim',
                    'station_code' => $station_code,
                    'is_prediction' => 1
                ));
                $imported++;
            }
            
            $msg = "Importert $imported tidevannsdata til databasen.";
        } else {
            $msg = "Kunne ikke åpne filen.";
        }
    }
    ?>
    <div class="wrap">
        <h1>Importer tidevannsdata fra fil</h1>
        <?php if ($msg) echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>'; ?>
        <p>Denne siden lar deg importere en <code>.txt</code> fil med tidevannsdata. Filen blir prosessert for å identifisere høy- og lavvannspunkter.</p>
        <form method="post" enctype="multipart/form-data">
            <p>
                <label for="tide_file">Velg tidevannsfil:</label><br>
                <input type="file" name="tide_file" id="tide_file" accept=".txt,.csv" required>
            </p>
            <button type="submit" name="import_tide_file" class="button button-primary">Importer</button>
        </form>
    </div>
    <?php
}
