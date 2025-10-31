<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="orkla-water-widget" data-period="<?php echo esc_attr($atts['period']); ?>">
    <?php if ($atts['show_controls'] === 'true'): ?>
    <div class="widget-controls">
        <label for="widget-period">Period:</label>
        <select id="widget-period">
            <option value="today" <?php selected($atts['period'], 'today'); ?>>Today</option>
            <option value="week" <?php selected($atts['period'], 'week'); ?>>Last 7 Days</option>
            <option value="month" <?php selected($atts['period'], 'month'); ?>>Last Month</option>
            <option value="year" <?php selected($atts['period'], 'year'); ?>>Last Year</option>
        </select>
        <button id="widget-refresh" class="orkla-button">Refresh</button>
    </div>
    <?php endif; ?>

    <div class="widget-header">
        <h3>Orkla Water Level Monitor</h3>
    </div>

    <div class="widget-chart-container" style="height: <?php echo esc_attr($atts['height']); ?>;">
        <canvas id="widget-chart"></canvas>
    </div>
</div>
