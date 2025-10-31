<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Water Level Archive</h1>
    
    <div class="orkla-archive-container">
        <div class="archive-controls">
            <div class="control-group">
                <label>Search Type:</label>
                <select id="archive-search-type">
                    <option value="day">Specific Day</option>
                    <option value="month">Specific Month</option>
                    <option value="year">Specific Year</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>Date:</label>
                <input type="date" id="archive-date" />
                <input type="month" id="archive-month" style="display: none;" />
                <input type="number" id="archive-year" min="2020" max="2030" style="display: none;" />
                <button id="search-archive" class="button button-primary">Search</button>
            </div>
        </div>
        
        <div class="archive-results">
            <div class="orkla-chart-container">
                <canvas id="archive-chart"></canvas>
            </div>
            
            <div class="archive-summary" id="archive-summary" style="display: none;">
                <h3>Archive Summary</h3>
                <div class="summary-stats">
                    <div class="stat-item">
                        <label>Period:</label>
                        <span id="summary-period">--</span>
                    </div>
                    <div class="stat-item">
                        <label>Average Level:</label>
                        <span id="summary-avg">--</span>
                    </div>
                    <div class="stat-item">
                        <label>Highest Level:</label>
                        <span id="summary-max">--</span>
                    </div>
                    <div class="stat-item">
                        <label>Lowest Level:</label>
                        <span id="summary-min">--</span>
                    </div>
                    <div class="stat-item">
                        <label>Data Points:</label>
                        <span id="summary-count">--</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="quick-links">
            <h3>Quick Access</h3>
            <div class="quick-link-buttons">
                <button class="button quick-search" data-type="day" data-value="<?php echo date('Y-m-d'); ?>">Today</button>
                <button class="button quick-search" data-type="day" data-value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">Yesterday</button>
                <button class="button quick-search" data-type="month" data-value="<?php echo date('Y-m'); ?>">This Month</button>
                <button class="button quick-search" data-type="month" data-value="<?php echo date('Y-m', strtotime('-1 month')); ?>">Last Month</button>
                <button class="button quick-search" data-type="year" data-value="<?php echo date('Y'); ?>">This Year</button>
                <button class="button quick-search" data-type="year" data-value="<?php echo date('Y', strtotime('-1 year')); ?>">Last Year</button>
            </div>
        </div>
    </div>
</div>