<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="orkla-water-widget" data-period="<?php echo esc_attr($atts['period']); ?>">
    <?php if ($atts['show_controls'] === 'true'): ?>
    <div class="widget-controls">
        <label for="widget-period">View:</label>
        <select id="widget-period">
            <option value="today" <?php selected($atts['period'], 'today'); ?>>Today</option>
            <option value="week" <?php selected($atts['period'], 'week'); ?>>Last 7 Days</option>
            <option value="month" <?php selected($atts['period'], 'month'); ?>>Last Month</option>
            <?php if (!empty($available_years)) : ?>
                <optgroup label="Available Years">
                    <?php foreach ($available_years as $year) : ?>
                        <?php $value = 'year:' . (int) $year; ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($atts['period'], $value); ?>><?php echo esc_html($year); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>

        <label for="widget-dataset">Data:</label>
        <select id="widget-dataset">
            <option value="flow" selected>Water Flow</option>
            <option value="temperature">Water Temperature</option>
        </select>

        <button id="widget-refresh" class="orkla-button">Refresh</button>
    </div>
    <?php endif; ?>
    
    <div class="widget-header">
        <h3>Orkla Water Level</h3>
    </div>
    
    <div class="widget-chart-container" style="height: <?php echo esc_attr($atts['height']); ?>;">
        <canvas id="widget-chart"></canvas>
    </div>
</div>