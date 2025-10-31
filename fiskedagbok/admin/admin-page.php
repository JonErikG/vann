<?php
// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

// Håndter sletting av fangst
global $wpdb;
$current_user_id = get_current_user_id();
$can_manage_all = current_user_can('manage_options');

$scope = 'mine';
if (isset($fiskedagbok_admin_scope)) {
    $scope = $fiskedagbok_admin_scope;
} elseif (isset($_GET['page']) && $_GET['page'] === 'fiskedagbok-all-catches') {
    $scope = 'all';
}

$per_page = 50;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;

$search_fisker = isset($_GET['fisker']) ? sanitize_text_field(wp_unslash($_GET['fisker'])) : '';
$search_vald = isset($_GET['vald']) ? sanitize_text_field(wp_unslash($_GET['vald'])) : '';
$search_year = isset($_GET['year']) ? absint($_GET['year']) : 0;

if ($scope === 'all' && !$can_manage_all) {
    $scope = 'mine';
}

if ($scope !== 'all' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_catch')) {
        $table_name_delete = $wpdb->prefix . 'fiskedagbok_catches';
        $catch_id = intval($_GET['id']);

        $where = array('id' => $catch_id);
        if (!$can_manage_all) {
            $where['user_id'] = $current_user_id;
        }

        $deleted = $wpdb->delete($table_name_delete, $where);

        if ($deleted) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Fangst slettet.', 'fiskedagbok') . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Fangst kunne ikke slettes eller du har ikke tilgang.', 'fiskedagbok') . '</p></div>';
        }
    }
}

$table_name = $scope === 'all'
    ? $wpdb->prefix . 'fiskedagbok_csv_archive'
    : $wpdb->prefix . 'fiskedagbok_catches';

$alias = $scope === 'all' ? 'a' : 'c';
$user_join = $scope === 'all'
    ? "LEFT JOIN {$wpdb->users} u ON a.claimed_by_user_id = u.ID"
    : "LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID";

$where_clauses = array();
$where_params = array();

if ($scope !== 'all') {
    $where_clauses[] = 'c.user_id = %d';
    $where_params[] = $current_user_id;
}

if ($search_fisker !== '') {
    $like = '%' . $wpdb->esc_like($search_fisker) . '%';
    $where_clauses[] = sprintf('(u.display_name LIKE %%s OR %s.fisher_name LIKE %%s)', $alias);
    $where_params[] = $like;
    $where_params[] = $like;
}

if ($search_vald !== '') {
    $like_vald = '%' . $wpdb->esc_like($search_vald) . '%';
    $where_clauses[] = sprintf('(%s.beat_name LIKE %%s OR %s.fishing_spot LIKE %%s)', $alias, $alias);
    $where_params[] = $like_vald;
    $where_params[] = $like_vald;
}

if ($search_year > 0) {
    $where_clauses[] = sprintf('YEAR(%s.date) = %%d', $alias);
    $where_params[] = $search_year;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

if ($scope === 'all') {
    $available_years_sql = "SELECT DISTINCT YEAR(date) as year FROM $table_name ORDER BY year DESC";
    $available_years = $wpdb->get_col($available_years_sql);
} else {
    $available_years_sql = $wpdb->prepare(
        "SELECT DISTINCT YEAR(date) as year FROM $table_name WHERE user_id = %d ORDER BY year DESC",
        $current_user_id
    );
    $available_years = $wpdb->get_col($available_years_sql);
}
$available_years = array_values(array_filter(array_map('intval', (array) $available_years)));

$total_sql = sprintf(
    'SELECT COUNT(*) FROM %1$s %2$s %3$s',
    $table_name . ' ' . $alias,
    $user_join,
    $where_sql
);
if (!empty($where_params)) {
    $total_sql = $wpdb->prepare($total_sql, $where_params);
}
$total_count = (int) $wpdb->get_var($total_sql);
$total_pages = max(1, (int) ceil($total_count / $per_page));

$select_fields = $alias . '.*';
if ($scope === 'all') {
    $select_fields .= ', u.display_name as user_name';
} else {
    $select_fields .= ', u.display_name as user_name';
}

$query_sql = sprintf(
    'SELECT %1$s FROM %2$s %3$s %4$s ORDER BY %5$s.date DESC, %5$s.time_of_day DESC LIMIT %%d OFFSET %%d',
    $select_fields,
    $table_name . ' ' . $alias,
    $user_join,
    $where_sql,
    $alias
);

$query_params = $where_params;
$query_params[] = $per_page;
$query_params[] = $offset;
$prepared_query = $wpdb->prepare($query_sql, $query_params);
$catches = $wpdb->get_results($prepared_query);

$heading = $scope === 'all'
    ? esc_html__('Fiskedagbok - Alle Fangster', 'fiskedagbok')
    : esc_html__('Fiskedagbok - Mine Fangster', 'fiskedagbok');

$mine_url = admin_url('admin.php?page=fiskedagbok');
$all_url = admin_url('admin.php?page=fiskedagbok-all-catches');
$delete_page_slug = $scope === 'all' ? 'fiskedagbok-all-catches' : 'fiskedagbok';

$pagination_query_args = array(
    'page' => $delete_page_slug,
);
if ($search_fisker !== '') {
    $pagination_query_args['fisker'] = $search_fisker;
}
if ($search_vald !== '') {
    $pagination_query_args['vald'] = $search_vald;
}
if ($search_year > 0) {
    $pagination_query_args['year'] = $search_year;
}

$pagination_base = add_query_arg($pagination_query_args, admin_url('admin.php'));
$pagination_links = paginate_links(array(
    'base'      => $pagination_base . '%_%',
    'format'    => '&paged=%#%',
    'current'   => $paged,
    'total'     => $total_pages,
    'add_args'  => false,
    'prev_text' => __('« Forrige', 'fiskedagbok'),
    'next_text' => __('Neste »', 'fiskedagbok'),
));
?>

<div class="wrap">
    <h1><?php echo $heading; ?></h1>

    <?php if ($scope === 'all' && $can_manage_all) : ?>
        <div class="notice notice-info" style="margin: 15px 0; padding: 12px;">
            <p>
                <strong>🌊 Tidevannsdata:</strong> 
                <a href="<?php echo esc_url(admin_url('admin.php?page=fiskedagbok-tidewater')); ?>" class="button button-secondary" style="margin-left: 10px;">
                    ⚙️ Gå til Tidevannsdata Admin
                </a>
                <em style="display: block; margin-top: 8px; color: #666;">
                    <?php 
                    // Get total from database if not set
                    if (!isset($total)) {
                        global $wpdb;
                        $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
                        $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
                        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $catches_table") + 
                                 (int) $wpdb->get_var("SELECT COUNT(*) FROM $archive_table");
                    }
                    echo 'Der kan du batch-importere tidevannsdata for alle ' . number_format_i18n($total) . ' fangstene.';
                    ?>
                </em>
            </p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 1em;">
        <a href="<?php echo esc_url($mine_url); ?>" class="nav-tab <?php echo $scope === 'mine' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Mine fangster', 'fiskedagbok'); ?></a>
        <?php if ($can_manage_all) : ?>
            <a href="<?php echo esc_url($all_url); ?>" class="nav-tab <?php echo $scope === 'all' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Alle fangster', 'fiskedagbok'); ?></a>
        <?php endif; ?>
    </h2>

    <div class="tablenav top">
        <div class="alignleft actions">
            <p>
                <?php
                $scope_text = $scope === 'all'
                    ? esc_html__('i databasen', 'fiskedagbok')
                    : esc_html__('registrert av deg', 'fiskedagbok');

                printf(
                    esc_html__('Totalt %1$d fangster %2$s', 'fiskedagbok'),
                    $total_count,
                    $scope_text
                );

                if ($total_pages > 1) {
                    printf(
                        ' · ' . esc_html__('Side %1$d av %2$d', 'fiskedagbok'),
                        $paged,
                        $total_pages
                    );
                }
                ?>
            </p>
        </div>
        <div class="alignright actions">
            <button id="refresh-weather-btn" class="button">🌤️ Oppdater værdata</button>
            <button id="force-refresh-weather-btn" class="button button-secondary">🔄 Oppdater alle fangster</button>
            <button id="test-weather-btn" class="button">🧪 Test værdata API</button>
        </div>
    </div>

    <form method="get" class="fiskedagbok-filter-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($delete_page_slug); ?>">
        <div class="fiskedagbok-filter-grid">
            <div>
                <label for="fiskedagbok-filter-fisker"><?php esc_html_e('Fisker', 'fiskedagbok'); ?></label>
                <input type="text" id="fiskedagbok-filter-fisker" name="fisker" value="<?php echo esc_attr($search_fisker); ?>" placeholder="<?php esc_attr_e('Søk på navn', 'fiskedagbok'); ?>">
            </div>
            <div>
                <label for="fiskedagbok-filter-vald"><?php esc_html_e('Vald / fiskeplass', 'fiskedagbok'); ?></label>
                <input type="text" id="fiskedagbok-filter-vald" name="vald" value="<?php echo esc_attr($search_vald); ?>" placeholder="<?php esc_attr_e('Søk på vald', 'fiskedagbok'); ?>">
            </div>
            <div>
                <label for="fiskedagbok-filter-year"><?php esc_html_e('År', 'fiskedagbok'); ?></label>
                <select id="fiskedagbok-filter-year" name="year">
                    <option value="0"><?php esc_html_e('Alle år', 'fiskedagbok'); ?></option>
                    <?php foreach ($available_years as $year_option) : ?>
                        <option value="<?php echo esc_attr($year_option); ?>" <?php selected((int) $year_option, $search_year); ?>><?php echo esc_html($year_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fiskedagbok-filter-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Filtrer', 'fiskedagbok'); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => $delete_page_slug), admin_url('admin.php'))); ?>"><?php esc_html_e('Nullstill', 'fiskedagbok'); ?></a>
            </div>
        </div>
    </form>

    <?php if ($pagination_links) : ?>
        <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div></div>
    <?php endif; ?>
    
    <?php if (!empty($catches)): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Dato</th>
                <th>Tid</th>
                <th>Fisker</th>
                <th>Elv</th>
                <th>Fiskeplass</th>
                <th>Fisketype</th>
                <th>Vekt (kg)</th>
                <th>Lengde (cm)</th>
                <th>Utstyr</th>
                <th>Sluppet</th>
                <th>Handlinger</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($catches as $catch): ?>
            <tr>
                <td><?php echo esc_html($catch->date); ?></td>
                <td><?php echo esc_html($catch->time_of_day); ?></td>
                <td><?php echo esc_html($catch->user_name ? $catch->user_name : $catch->fisher_name); ?></td>
                <td><?php echo esc_html($catch->river_name); ?></td>
                <td><?php echo esc_html($catch->fishing_spot ? $catch->fishing_spot : $catch->beat_name); ?></td>
                <td><?php echo esc_html($catch->fish_type); ?></td>
                <td><?php echo esc_html($catch->weight_kg); ?></td>
                <td><?php echo esc_html($catch->length_cm); ?></td>
                <td><?php echo esc_html($catch->equipment); ?></td>
                <td><?php echo $catch->released ? 'Ja' : 'Nei'; ?></td>
                <td>
                    <?php if ($scope === 'all') : ?>
                        &mdash;
                    <?php else : ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=' . $delete_page_slug . '&action=delete&id=' . $catch->id), 'delete_catch'); ?>"
                           onclick="return confirm('<?php echo esc_js(__('Er du sikker på at du vil slette denne fangsten?', 'fiskedagbok')); ?>');"><?php esc_html_e('Slett', 'fiskedagbok'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pagination_links) : ?>
        <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div></div>
    <?php endif; ?>
    <?php else: ?>
    <p><?php esc_html_e('Ingen fangster funnet.', 'fiskedagbok'); ?></p>
    <?php endif; ?>
</div>

<h3>🌤️ Værdata administrasjon</h3>
<p>Konfigurer Frost API-nøkler og testing under <strong>Fiskedagbok → Værdata API</strong> i menyen.</p>

<p>
    <button id="test-weather-btn" class="button">🌤️ Test vær-API (Meldal koordinater)</button>
    <button id="refresh-weather-btn" class="button">🔄 Oppdater værdata</button>
    <button id="force-refresh-weather-btn" class="button">🔄 Oppdater alle fangster</button>
</p>
<div id="weather-test-result"></div>

<style>
.wp-list-table th,
.wp-list-table td {
    padding: 8px 10px;
}
#weather-progress {
    background: #f1f1f1;
    border-radius: 3px;
    padding: 10px;
    margin: 10px 0;
    display: none;
}
.fiskedagbok-filter-form {
    margin: 15px 0;
    padding: 12px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}
.fiskedagbok-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    align-items: end;
}
.fiskedagbok-filter-grid label {
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
}
.fiskedagbok-filter-actions {
    display: flex;
    gap: 8px;
}
</style>

<div class="wrap">
    <h2>Verktøy</h2>
    <ul>
        <li><a href="admin.php?page=import-tide-file">Importer tidevannsdata fra fil</a></li>
    </ul>
</div>
<script>
jQuery(function($) {
    const fiskedagbokNonce = '<?php echo wp_create_nonce("fiskedagbok_nonce"); ?>';

    function ensureProgressContainer($anchor, initialMessage) {
        let $container = $('#weather-progress');
        if ($container.length === 0) {
            $anchor.after('<div id="weather-progress"></div>');
            $container = $('#weather-progress');
        }
        if (initialMessage) {
            $container.html('<p>' + initialMessage + '</p>');
        }
        $container.show();
        return $container;
    }

    $('#test-weather-btn').off('click').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Tester...');

        if ($('#weather-test-result').length === 0) {
            $button.after('<div id="weather-test-result"></div>');
        }
        $('#weather-test-result').html('<p>🌤️ Tester vær-API med Meldal koordinater (63.048, 9.713)...</p>').show();

        $.post(ajaxurl, {
            action: 'test_weather_api',
            nonce: fiskedagbokNonce
        })
        .done(function(response) {
            if (response.success) {
                $('#weather-test-result').html(
                    '<div style="background: #d1e7dd; border: 1px solid #badbcc; padding: 10px; border-radius: 3px;">' +
                    '<h4>✅ API Test Vellykket!</h4>' +
                    '<p><strong>Lokasjon:</strong> Meldal/Orkland (63.048°N, 9.713°E)</p>' +
                    '<p><strong>API:</strong> ' + response.data.source + '</p>' +
                    '<p><strong>Temperatur:</strong> ' + response.data.temperature + '°C</p>' +
                    '<p><strong>Vind:</strong> ' + response.data.wind_speed + ' m/s fra ' + response.data.wind_direction + '°</p>' +
                    '<p><strong>Nedbør:</strong> ' + response.data.precipitation + ' mm</p>' +
                    '</div>'
                );
            } else {
                $('#weather-test-result').html(
                    '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 3px;">' +
                    '<h4>❌ API Test feilet</h4>' +
                    '<p>' + response.data + '</p>' +
                    '</div>'
                );
            }
        })
        .fail(function() {
            $('#weather-test-result').html(
                '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 3px;">' +
                '<h4>❌ AJAX feil</h4>' +
                '<p>Kunne ikke kontakte serveren</p>' +
                '</div>'
            );
        })
        .always(function() {
            $button.prop('disabled', false).text('🌤️ Test vær-API (Meldal koordinater)');
        });
    });

    $('#refresh-weather-btn').off('click').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();

        $button.prop('disabled', true).text('Oppdaterer...');
        const $progress = ensureProgressContainer($button, 'Oppdaterer fangster med utgått eller manglende værdata...');

        $.post(ajaxurl, {
            action: 'refresh_weather_data',
            nonce: fiskedagbokNonce
        })
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                const waterLine = typeof data.water_updated !== 'undefined'
                    ? '<p>Vanntemperatur lagret: ' + data.water_updated + (data.water_errors ? ' (feil ' + data.water_errors + ')' : '') + '</p>'
                    : '';
                $progress.html(
                    '<p>✅ Værdata oppdatert.</p>' +
                    '<p>Oppdatert: ' + data.updated + ' | Feil: ' + data.errors + '</p>' +
                    waterLine +
                    '<p>Totalt behandlet: ' + data.total_processed + '</p>'
                );

                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                $progress.html('<p>❌ Feil: ' + response.data + '</p>');
            }
        })
        .fail(function() {
            $progress.html('<p>❌ Teknisk feil oppstod.</p>');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });

    $('#force-refresh-weather-btn').off('click').on('click', function() {
        if (!confirm('Dette vil oppdatere vær- og vanndata for alle fangster og kan ta tid. Fortsette?')) {
            return;
        }

        const $button = $(this);
        const originalText = $button.text();
        const $refreshButton = $('#refresh-weather-btn');
        const $testButton = $('#test-weather-btn');

        $button.prop('disabled', true).text('Oppdaterer alle...');
        $refreshButton.prop('disabled', true);
        $testButton.prop('disabled', true);

        const $progress = ensureProgressContainer($button, 'Starter oppdatering for alle fangster...');

        let offset = 0;
        const batchSize = 50;
        let totalProcessed = 0;
        let totalUpdated = 0;
        let totalErrors = 0;
        let totalWaterUpdated = 0;
        let totalWaterErrors = 0;
        let totalCandidates = null;

        function renderProgress() {
            const processedDisplay = totalCandidates !== null ? Math.min(totalProcessed, totalCandidates) : totalProcessed;
            const totalDisplay = totalCandidates !== null ? ' av ' + totalCandidates : '';
            $progress.html(
                '<p>🔄 Behandlet ' + processedDisplay + totalDisplay + ' fangster...</p>' +
                '<p>🌤️ Værdata oppdatert: ' + totalUpdated + ' (feil ' + totalErrors + ')</p>' +
                '<p>🌊 Vanntemperatur lagret: ' + totalWaterUpdated + (totalWaterErrors ? ' (feil ' + totalWaterErrors + ')' : '') + '</p>'
            );
        }

        function finish(success) {
            $button.prop('disabled', false).text(originalText);
            $refreshButton.prop('disabled', false);
            $testButton.prop('disabled', false);
            if (!success) {
                $progress.append('<p>❌ Oppdateringen ble stoppet.</p>');
            }
        }

        function processBatch() {
            $.post(ajaxurl, {
                action: 'refresh_weather_data',
                force_update: 'true',
                batch_size: batchSize,
                offset: offset,
                nonce: fiskedagbokNonce
            })
            .done(function(response) {
                if (!response.success) {
                    $progress.html('<p>❌ Feil: ' + response.data + '</p>');
                    finish(false);
                    return;
                }

                const data = response.data;
                totalProcessed += data.total_processed;
                totalUpdated += data.updated;
                totalErrors += data.errors;
                totalWaterUpdated += data.water_updated || 0;
                totalWaterErrors += data.water_errors || 0;

                if (totalCandidates === null && typeof data.total_candidates !== 'undefined') {
                    totalCandidates = data.total_candidates;
                }

                renderProgress();

                if (data.has_more) {
                    offset = data.next_offset;
                    processBatch();
                } else {
                    $progress.html(
                        '<p>✅ Alle fangster er oppdatert.</p>' +
                        '<p>Totalt behandlet: ' + totalProcessed + '</p>' +
                        '<p>🌤️ Værdata oppdatert: ' + totalUpdated + ' (feil ' + totalErrors + ')</p>' +
                        '<p>🌊 Vanntemperatur lagret: ' + totalWaterUpdated + (totalWaterErrors ? ' (feil ' + totalWaterErrors + ')' : '') + '</p>'
                    );
                    finish(true);
                    setTimeout(function() {
                        location.reload();
                    }, 5000);
                }
            })
            .fail(function() {
                $progress.html('<p>❌ Teknisk feil oppstod under oppdateringen.</p>');
                finish(false);
            });
        }

        renderProgress();
        processBatch();
    });
});
</script>