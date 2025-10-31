<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="orkla-archive-widget">
    <div class="archive-widget-header">
        <h3>Water Level Archive</h3>
    </div>
    
    <div class="archive-widget-controls">
        <div class="control-row">
            <select id="archive-widget-type">
                <option value="day">Daily View</option>
                <option value="month">Monthly View</option>
                <option value="year">Yearly View</option>
            </select>
            
            <input type="date" id="archive-widget-date" />
            <input type="month" id="archive-widget-month" style="display: none;" />
            <input type="number" id="archive-widget-year" min="2020" max="2030" style="display: none;" />
            
            <button id="archive-widget-search" class="orkla-button">View</button>
        </div>
    </div>
    
    <div class="archive-widget-chart" style="height: <?php echo esc_attr($atts['height']); ?>;">
        <canvas id="archive-widget-chart"></canvas>
    </div>
    
    <div class="archive-widget-summary" style="display: none;">
        <div class="summary-row">
            <div class="summary-item">
                <span class="label">Period:</span>
                <span class="value" id="archive-widget-period">--</span>
            </div>
            <div class="summary-item">
                <span class="label">Avg Level:</span>
                <span class="value" id="archive-widget-avg">--</span>
            </div>
            <div class="summary-item">
                <span class="label">High:</span>
                <span class="value" id="archive-widget-max">--</span>
            </div>
            <div class="summary-item">
                <span class="label">Low:</span>
                <span class="value" id="archive-widget-min">--</span>
            </div>
        </div>
    </div>
</div>