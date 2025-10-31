<?php
if (!defined('ABSPATH')) {
    exit;
}

$admin_messages = isset($admin_messages) && is_array($admin_messages) ? $admin_messages : array('errors' => array(), 'notices' => array());
$csv_sources = isset($csv_sources) && is_array($csv_sources) ? $csv_sources : array();
$available_years = isset($available_years) && is_array($available_years) ? $available_years : array();
$csv_base_path = isset($csv_base_path) ? $csv_base_path : '';

$csv_summary_data = array();
$csv_summary_completed_at = null;

if (isset($csv_summary) && is_array($csv_summary)) {
    if (isset($csv_summary['summary']) && is_array($csv_summary['summary'])) {
        $csv_summary_data = $csv_summary['summary'];
    }
    if (!empty($csv_summary['completed_at'])) {
        $csv_summary_completed_at = $csv_summary['completed_at'];
    }
}

$notice_errors = !empty($admin_messages['errors']) ? array_unique(array_map('wp_strip_all_tags', $admin_messages['errors'])) : array();
$notice_success = !empty($admin_messages['notices']) ? array_unique(array_map('wp_strip_all_tags', $admin_messages['notices'])) : array();
?>
<div class="wrap">
    <h1><?php esc_html_e('Orkla Vannføring Monitor', 'orkla-water-level'); ?></h1>

    <?php if (!empty($notice_errors)) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html(implode(' ', $notice_errors)); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($notice_success)) : ?>
        <div class="notice notice-success">
            <p><?php echo esc_html(implode(' ', $notice_success)); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($csv_summary_data)) : ?>
        <?php
            $summary_parts = array();
            if (isset($csv_summary_data['imported'])) {
                $summary_parts[] = sprintf(__('Inserted: %d', 'orkla-water-level'), (int) $csv_summary_data['imported']);
            }
            if (isset($csv_summary_data['updated'])) {
                $summary_parts[] = sprintf(__('Updated: %d', 'orkla-water-level'), (int) $csv_summary_data['updated']);
            }
            if (isset($csv_summary_data['skipped'])) {
                $summary_parts[] = sprintf(__('Skipped: %d', 'orkla-water-level'), (int) $csv_summary_data['skipped']);
            }
            if (isset($csv_summary_data['record_count'])) {
                $summary_parts[] = sprintf(__('Records processed: %d', 'orkla-water-level'), (int) $csv_summary_data['record_count']);
            }
            $summary_timestamp = $csv_summary_completed_at ? mysql2date('Y-m-d H:i:s', $csv_summary_completed_at) : __('unknown time', 'orkla-water-level');
        ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    /* translators: %s: datetime string */
                    esc_html__('Last CSV import completed at %s.', 'orkla-water-level'),
                    esc_html($summary_timestamp)
                );
                ?>
            </p>
            <?php if (!empty($summary_parts)) : ?>
                <p><?php echo esc_html(implode(' · ', $summary_parts)); ?></p>
            <?php endif; ?>
            <?php if (!empty($csv_summary_data['errors']) && is_array($csv_summary_data['errors'])) : ?>
                <?php
                $error_preview = array_slice(array_filter(array_map('wp_strip_all_tags', $csv_summary_data['errors'])), 0, 3);
                if (!empty($error_preview)) :
                ?>
                    <p><?php esc_html_e('Import errors:', 'orkla-water-level'); ?> <?php echo esc_html(implode(' | ', $error_preview)); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="orkla-admin-container">
        <div class="orkla-controls">
            <h2><?php esc_html_e('Aktuell Vannføring Data', 'orkla-water-level'); ?></h2>
            <div class="control-group">
                <label for="period-select"><?php esc_html_e('View Period:', 'orkla-water-level'); ?></label>
                <select id="period-select">
                    <option value="today"><?php esc_html_e('Today', 'orkla-water-level'); ?></option>
                    <option value="week"><?php esc_html_e('Last 7 Days', 'orkla-water-level'); ?></option>
                    <option value="month" selected><?php esc_html_e('Last Month', 'orkla-water-level'); ?></option>
                    <?php if (!empty($available_years)) : ?>
                        <optgroup label="<?php esc_attr_e('Available Years', 'orkla-water-level'); ?>">
                            <?php foreach ($available_years as $year) : ?>
                                <?php $value = 'year:' . (int) $year; ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($year); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>

                <label for="dataset-select"><?php esc_html_e('Data Type:', 'orkla-water-level'); ?></label>
                <select id="dataset-select">
                    <option value="flow" selected><?php esc_html_e('Water Flow', 'orkla-water-level'); ?></option>
                    <option value="temperature"><?php esc_html_e('Water Temperature', 'orkla-water-level'); ?></option>
                </select>
                <button id="refresh-data" class="button button-primary"><?php esc_html_e('Refresh Data', 'orkla-water-level'); ?></button>
                <button id="fetch-now" class="button button-secondary"><?php esc_html_e('Run CSV Import (New Data)', 'orkla-water-level'); ?></button>
                <button id="full-import" class="button button-secondary" style="background: #d63638; color: white; border-color: #d63638;" onclick="if(confirm('This will import ALL historical data from the CSV file. Continue?')) { location.href='<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&csv_fetch_now=1&full_import=1')); ?>'; } return false;"><?php esc_html_e('Full Historical Import', 'orkla-water-level'); ?></button>
                <button id="test-csv" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_csv=1')); ?>', '_blank'); return false;"><?php esc_html_e('Diagnose CSV Sources', 'orkla-water-level'); ?></button>
                <button id="test-single-line" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_single=1')); ?>', '_blank'); return false;"><?php esc_html_e('Parse Sample Row', 'orkla-water-level'); ?></button>
                <button id="test-remote" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_remote_download=1')); ?>', '_blank'); return false;"><?php esc_html_e('Test Remote Download', 'orkla-water-level'); ?></button>
                <button id="test-full-import" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_full_import=1')); ?>', '_blank'); return false;"><?php esc_html_e('Test Full Import', 'orkla-water-level'); ?></button>
                <button id="test-import-detail" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_import_detail=1')); ?>', '_blank'); return false;">Import Detail Test</button>
                <button id="test-data-fetch" class="button button-secondary" onclick="window.open('<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&test_data_fetch=1')); ?>', '_blank'); return false;">Test Data Fetch</button>
                <button id="reschedule-cron" class="button button-secondary" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=orkla-water-level&reschedule=1')); ?>'; return false;"><?php esc_html_e('Reschedule Cron', 'orkla-water-level'); ?></button>
            </div>
        </div>

        <div class="orkla-chart-container">
            <canvas id="vannforing-chart"></canvas>
        </div>

        <div class="orkla-stats">
            <div class="stat-card">
                <h3><?php esc_html_e('Aktuell Vannføring', 'orkla-water-level'); ?></h3>
                <span id="current-level">--</span>
            </div>
            <div class="stat-card">
                <h3><?php esc_html_e('24t Gjennomsnitt', 'orkla-water-level'); ?></h3>
                <span id="avg-level">--</span>
            </div>
            <div class="stat-card">
                <h3><?php esc_html_e('24t Høyest', 'orkla-water-level'); ?></h3>
                <span id="max-level">--</span>
            </div>
            <div class="stat-card">
                <h3><?php esc_html_e('24t Lavest', 'orkla-water-level'); ?></h3>
                <span id="min-level">--</span>
            </div>
        </div>

        <div class="orkla-info">
            <h3><?php esc_html_e('Importer Information', 'orkla-water-level'); ?></h3>
            <p><?php esc_html_e('CSV Base Path:', 'orkla-water-level'); ?> <?php echo esc_html($csv_base_path); ?></p>
            <p><?php esc_html_e('Update Frequency: Hourly via cron job', 'orkla-water-level'); ?></p>
            <p>
                <?php
                $next_scheduled = wp_next_scheduled('orkla_fetch_data_hourly');
                if ($next_scheduled) {
                    printf(
                        esc_html__('Next scheduled run: %s', 'orkla-water-level'),
                        esc_html(date_i18n('Y-m-d H:i:s', $next_scheduled))
                    );
                } else {
                    esc_html_e('Next scheduled run: not scheduled', 'orkla-water-level');
                }

                $test_scheduled = wp_next_scheduled('orkla_test_cron');
                if ($test_scheduled) {
                    echo '<br>' . esc_html(sprintf(__('Test cron run: %s', 'orkla-water-level'), date_i18n('Y-m-d H:i:s', $test_scheduled)));
                }
                ?>
            </p>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'orkla_water_data';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if ($table_exists) :
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 1");
            ?>
                <p><?php esc_html_e('Total records:', 'orkla-water-level'); ?> <?php echo esc_html(number_format_i18n($count)); ?></p>
                <?php if ($latest) : ?>
                    <p>
                        <?php
                        printf(
                            esc_html__('Latest record: %1$s (Syrstad: %2$s m³/s)', 'orkla-water-level'),
                            esc_html($latest->timestamp),
                            esc_html(isset($latest->water_level_2) ? $latest->water_level_2 : '--')
                        );
                        ?>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p><?php esc_html_e('Database table does not exist yet.', 'orkla-water-level'); ?></p>
            <?php endif; ?>
        </div>

        <div class="orkla-info">
            <h3><?php esc_html_e('CSV Kilder', 'orkla-water-level'); ?></h3>
            <style>
                .dataset-tools form {
                    margin-bottom: 8px;
                }

                .dataset-tools input[type="file"] {
                    width: 100%;
                    margin: 6px 0;
                }
            </style>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Felt', 'orkla-water-level'); ?></th>
                        <th><?php esc_html_e('Fil', 'orkla-water-level'); ?></th>
                        <th><?php esc_html_e('Status', 'orkla-water-level'); ?></th>
                        <th><?php esc_html_e('Siste import', 'orkla-water-level'); ?></th>
                        <th><?php esc_html_e('Verktøy', 'orkla-water-level'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($csv_sources as $field => $source) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($source['label']); ?></strong><br>
                                <small><?php echo esc_html($field); ?></small>
                            </td>
                            <td>
                                <?php if (!$source['configured']) : ?>
                                    <span><?php esc_html_e('Not configured', 'orkla-water-level'); ?></span>
                                <?php elseif (!$source['file']) : ?>
                                    <span><?php esc_html_e('File not specified', 'orkla-water-level'); ?></span>
                                <?php else : ?>
                                    <span><?php echo esc_html($source['file']); ?></span>
                                    <?php if (!empty($source['filesize'])) : ?>
                                        <br><small><?php echo esc_html(size_format($source['filesize'], 2)); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$source['configured']) : ?>
                                    <span class="dashicons dashicons-warning" style="color:#d63638;"></span>
                                    <?php esc_html_e('Needs configuration', 'orkla-water-level'); ?>
                                <?php elseif ($source['exists']) : ?>
                                    <span class="dashicons dashicons-yes" style="color:#46b450;"></span>
                                    <?php esc_html_e('File ready', 'orkla-water-level'); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-dismiss" style="color:#d63638;"></span>
                                    <?php esc_html_e('File missing', 'orkla-water-level'); ?>
                                <?php endif; ?>
                                <?php if ($source['exists'] && !empty($source['path'])) : ?>
                                    <br><small><?php echo esc_html($source['path']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($source['remote_url'])) : ?>
                                    <br><small><?php esc_html_e('Remote:', 'orkla-water-level'); ?> <?php echo esc_html($source['remote_url']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($source['warnings'])) : ?>
                                    <br><small style="color:#d9822b;"><?php echo esc_html(implode(' | ', (array) $source['warnings'])); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($source['errors'])) : ?>
                                    <br><small style="color:#d63638;"><?php echo esc_html(implode(' | ', (array) $source['errors'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($source['last_import']) && is_array($source['last_import'])) {
                                    $last = $source['last_import'];
                                    $parts = array();

                                    if (!empty($last['rows_imported'])) {
                                        $parts[] = sprintf(__('rows: %d', 'orkla-water-level'), (int) $last['rows_imported']);
                                    }
                                    if (!empty($last['first_timestamp']) && !empty($last['last_timestamp'])) {
                                        $parts[] = sprintf('%s → %s', $last['first_timestamp'], $last['last_timestamp']);
                                    } elseif (!empty($last['last_timestamp'])) {
                                        $parts[] = $last['last_timestamp'];
                                    }

                                    if (!empty($parts)) {
                                        echo '<small>' . esc_html(implode(' · ', $parts)) . '</small>';
                                    } else {
                                        esc_html_e('No metrics collected yet.', 'orkla-water-level');
                                    }
                                } else {
                                    esc_html_e('No import history yet.', 'orkla-water-level');
                                }
                                ?>
                            </td>
                            <td>
                                <div class="dataset-tools">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=orkla-water-level')); ?>" onsubmit="return confirm('<?php echo esc_js(sprintf(__('Er du sikker på at du vil slette alle verdier for %s?', 'orkla-water-level'), $source['label'])); ?>');">
                                        <?php wp_nonce_field('orkla_dataset_action'); ?>
                                        <input type="hidden" name="page" value="orkla-water-level">
                                        <input type="hidden" name="dataset_key" value="<?php echo esc_attr($field); ?>">
                                        <input type="hidden" name="orkla_dataset_action" value="delete">
                                        <button type="submit" class="button button-secondary"><?php esc_html_e('Slett data', 'orkla-water-level'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=orkla-water-level')); ?>" enctype="multipart/form-data" class="dataset-upload-form">
                                        <?php wp_nonce_field('orkla_dataset_action'); ?>
                                        <input type="hidden" name="page" value="orkla-water-level">
                                        <input type="hidden" name="dataset_key" value="<?php echo esc_attr($field); ?>">
                                        <input type="hidden" name="orkla_dataset_action" value="upload">
                                        <label class="screen-reader-text" for="dataset-file-<?php echo esc_attr($field); ?>"><?php echo esc_html(sprintf(__('Last opp CSV for %s', 'orkla-water-level'), $source['label'])); ?></label>
                                        <input type="file" id="dataset-file-<?php echo esc_attr($field); ?>" name="dataset_file" accept=".csv,text/csv" required>
                                        <button type="submit" class="button button-primary"><?php esc_html_e('Last opp CSV', 'orkla-water-level'); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>