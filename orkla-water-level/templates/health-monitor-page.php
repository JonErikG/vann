<?php
if (!defined('ABSPATH')) {
    exit;
}

$monitor = new Orkla_Health_Monitor();
$health_check = $monitor->run_health_check();
$statistics = $monitor->get_data_statistics();
$gaps = $monitor->detect_data_gaps();

$status_colors = array(
    'healthy' => '#10b981',
    'warning' => '#f59e0b',
    'critical' => '#ef4444',
    'ok' => '#10b981',
    'error' => '#ef4444',
);

$status_color = isset($status_colors[$health_check['status']]) ? $status_colors[$health_check['status']] : '#6b7280';
?>

<div class="wrap orkla-health-monitor">
    <h1><?php _e('System Health Monitor', 'orkla-water-level'); ?></h1>

    <div class="orkla-health-status-card" style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($status_color); ?>;">
        <h2 style="margin-top: 0;">
            <?php _e('Overall Status:', 'orkla-water-level'); ?>
            <span style="color: <?php echo esc_attr($status_color); ?>; text-transform: uppercase;">
                <?php echo esc_html($health_check['status']); ?>
            </span>
        </h2>
        <p><strong><?php _e('Last Check:', 'orkla-water-level'); ?></strong> <?php echo esc_html($health_check['timestamp']); ?></p>

        <?php if (!empty($health_check['errors'])): ?>
            <div class="notice notice-error">
                <p><strong><?php _e('Errors:', 'orkla-water-level'); ?></strong></p>
                <ul>
                    <?php foreach ($health_check['errors'] as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($health_check['warnings'])): ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Warnings:', 'orkla-water-level'); ?></strong></p>
                <ul>
                    <?php foreach ($health_check['warnings'] as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="orkla-health-checks" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
        <?php foreach ($health_check['checks'] as $check_name => $check_result): ?>
            <?php
            $check_color = isset($status_colors[$check_result['status']]) ? $status_colors[$check_result['status']] : '#6b7280';
            ?>
            <div class="orkla-check-card" style="background: white; padding: 15px; border-left: 3px solid <?php echo esc_attr($check_color); ?>;">
                <h3 style="margin-top: 0; text-transform: capitalize;">
                    <?php echo esc_html(str_replace('_', ' ', $check_name)); ?>
                </h3>
                <p><strong><?php _e('Status:', 'orkla-water-level'); ?></strong>
                    <span style="color: <?php echo esc_attr($check_color); ?>;">
                        <?php echo esc_html($check_result['status']); ?>
                    </span>
                </p>
                <p><?php echo esc_html($check_result['message']); ?></p>

                <?php if (isset($check_result['record_count'])): ?>
                    <p><strong><?php _e('Records:', 'orkla-water-level'); ?></strong> <?php echo number_format($check_result['record_count']); ?></p>
                <?php endif; ?>

                <?php if (isset($check_result['latest_timestamp'])): ?>
                    <p><strong><?php _e('Latest Data:', 'orkla-water-level'); ?></strong> <?php echo esc_html($check_result['latest_timestamp']); ?></p>
                <?php endif; ?>

                <?php if (isset($check_result['age_hours'])): ?>
                    <p><strong><?php _e('Age:', 'orkla-water-level'); ?></strong> <?php echo esc_html($check_result['age_hours']); ?> <?php _e('hours', 'orkla-water-level'); ?></p>
                <?php endif; ?>

                <?php if (isset($check_result['next_run'])): ?>
                    <p><strong><?php _e('Next Run:', 'orkla-water-level'); ?></strong> <?php echo esc_html($check_result['next_run']); ?></p>
                <?php endif; ?>

                <?php if (!empty($check_result['issues'])): ?>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <?php foreach ($check_result['issues'] as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="orkla-statistics" style="background: white; padding: 20px; margin: 20px 0;">
        <h2><?php _e('Data Statistics', 'orkla-water-level'); ?></h2>

        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><?php _e('Metric', 'orkla-water-level'); ?></th>
                    <th><?php _e('Value', 'orkla-water-level'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php _e('Total Records', 'orkla-water-level'); ?></strong></td>
                    <td><?php echo number_format($statistics['total_records']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Records Last 24h', 'orkla-water-level'); ?></strong></td>
                    <td><?php echo number_format($statistics['records_last_24h']); ?></td>
                </tr>
                <?php if (!empty($statistics['date_range'])): ?>
                    <tr>
                        <td><strong><?php _e('Earliest Record', 'orkla-water-level'); ?></strong></td>
                        <td><?php echo esc_html($statistics['date_range']['earliest']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Latest Record', 'orkla-water-level'); ?></strong></td>
                        <td><?php echo esc_html($statistics['date_range']['latest']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($statistics['average_values'])): ?>
                    <tr>
                        <td><strong><?php _e('Avg Water Level (7 days)', 'orkla-water-level'); ?></strong></td>
                        <td><?php echo round($statistics['average_values']['avg_water_level'], 2); ?> m³/s</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Max Water Level (7 days)', 'orkla-water-level'); ?></strong></td>
                        <td><?php echo round($statistics['average_values']['max_water_level'], 2); ?> m³/s</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Min Water Level (7 days)', 'orkla-water-level'); ?></strong></td>
                        <td><?php echo round($statistics['average_values']['min_water_level'], 2); ?> m³/s</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3 style="margin-top: 30px;"><?php _e('Field Coverage', 'orkla-water-level'); ?></h3>
        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><?php _e('Field', 'orkla-water-level'); ?></th>
                    <th><?php _e('Records', 'orkla-water-level'); ?></th>
                    <th><?php _e('Coverage', 'orkla-water-level'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statistics['field_coverage'] as $field => $coverage): ?>
                    <tr>
                        <td><strong><?php echo esc_html($field); ?></strong></td>
                        <td><?php echo number_format($coverage['count']); ?></td>
                        <td>
                            <span style="display: inline-block; width: 60px;"><?php echo esc_html($coverage['percentage']); ?>%</span>
                            <div style="display: inline-block; width: 200px; height: 20px; background: #e5e7eb; border-radius: 10px; overflow: hidden;">
                                <div style="width: <?php echo esc_attr($coverage['percentage']); ?>%; height: 100%; background: #10b981;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($gaps)): ?>
        <div class="orkla-data-gaps" style="background: white; padding: 20px; margin: 20px 0;">
            <h2><?php _e('Data Gaps Detected', 'orkla-water-level'); ?></h2>
            <p><?php _e('The following gaps (>2 hours) were detected in the last 7 days:', 'orkla-water-level'); ?></p>

            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Start', 'orkla-water-level'); ?></th>
                        <th><?php _e('End', 'orkla-water-level'); ?></th>
                        <th><?php _e('Gap Duration', 'orkla-water-level'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gaps as $gap): ?>
                        <tr>
                            <td><?php echo esc_html($gap['start']); ?></td>
                            <td><?php echo esc_html($gap['end']); ?></td>
                            <td><?php echo esc_html($gap['gap_hours']); ?> <?php _e('hours', 'orkla-water-level'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="orkla-actions" style="background: white; padding: 20px; margin: 20px 0;">
        <h2><?php _e('Actions', 'orkla-water-level'); ?></h2>

        <p>
            <button type="button" class="button button-primary" id="orkla-refresh-health-check">
                <?php _e('Refresh Health Check', 'orkla-water-level'); ?>
            </button>
        </p>

        <hr style="margin: 20px 0;">

        <h3><?php _e('Maintenance', 'orkla-water-level'); ?></h3>
        <p><?php _e('Clean up old data to improve database performance:', 'orkla-water-level'); ?></p>

        <p>
            <label for="cleanup-days"><?php _e('Keep data from the last:', 'orkla-water-level'); ?></label>
            <select id="cleanup-days" style="margin-left: 10px;">
                <option value="365"><?php _e('1 year', 'orkla-water-level'); ?></option>
                <option value="730"><?php _e('2 years', 'orkla-water-level'); ?></option>
                <option value="1095"><?php _e('3 years', 'orkla-water-level'); ?></option>
            </select>
            <button type="button" class="button" id="orkla-cleanup-old-data" style="margin-left: 10px;">
                <?php _e('Cleanup Old Data', 'orkla-water-level'); ?>
            </button>
        </p>

        <div id="orkla-action-result" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#orkla-refresh-health-check').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('<?php esc_attr_e('Refreshing...', 'orkla-water-level'); ?>');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'orkla_run_health_check',
                nonce: '<?php echo wp_create_nonce('orkla_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php esc_attr_e('Health check failed', 'orkla-water-level'); ?>');
                    btn.prop('disabled', false).text('<?php esc_attr_e('Refresh Health Check', 'orkla-water-level'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_attr_e('Request failed', 'orkla-water-level'); ?>');
                btn.prop('disabled', false).text('<?php esc_attr_e('Refresh Health Check', 'orkla-water-level'); ?>');
            }
        });
    });

    $('#orkla-cleanup-old-data').on('click', function() {
        var days = $('#cleanup-days').val();
        var message = '<?php esc_attr_e('This will delete all records older than', 'orkla-water-level'); ?> ' +
                      $('#cleanup-days option:selected').text() + '. <?php esc_attr_e('Continue?', 'orkla-water-level'); ?>';

        if (!confirm(message)) {
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('<?php esc_attr_e('Cleaning...', 'orkla-water-level'); ?>');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'orkla_cleanup_old_data',
                nonce: '<?php echo wp_create_nonce('orkla_nonce'); ?>',
                days: days
            },
            success: function(response) {
                var result = $('#orkla-action-result');
                if (response.success) {
                    result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    btn.prop('disabled', false).text('<?php esc_attr_e('Cleanup Old Data', 'orkla-water-level'); ?>');
                }
            },
            error: function() {
                $('#orkla-action-result').html('<div class="notice notice-error"><p><?php esc_attr_e('Request failed', 'orkla-water-level'); ?></p></div>');
                btn.prop('disabled', false).text('<?php esc_attr_e('Cleanup Old Data', 'orkla-water-level'); ?>');
            }
        });
    });
});
</script>
