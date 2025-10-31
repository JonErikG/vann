<?php
/**
 * Fix foreign key constraint for tidewater_data table
 * Access via: /wp-content/plugins/fiskedagbok/fix-foreign-key.php
 * 
 * This removes the foreign key constraint that prevents inserting catch_id from archive table
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;

echo '<h1>🔧 Fix Foreign Key Constraint</h1>';

$table_name = $wpdb->prefix . 'fiskedagbok_tidewater_data';

// Get current table status
echo '<h2>Current Table Status</h2>';
$table_info = $wpdb->get_row("SHOW CREATE TABLE $table_name");
echo '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">' . htmlspecialchars($table_info->{'Create Table'}) . '</pre>';

echo '<h2>Foreign Key Constraints</h2>';

// Get foreign keys
$fks = $wpdb->get_results("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = '" . str_replace($wpdb->prefix, '', $table_name) . "'
    AND TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

if (empty($fks)) {
    echo '<p style="color: green;"><strong>✓ No foreign key constraints found!</strong></p>';
} else {
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr><th>Constraint Name</th><th>Column</th><th>References</th></tr>';
    foreach ($fks as $fk) {
        echo '<tr>';
        echo '<td>' . $fk->CONSTRAINT_NAME . '</td>';
        echo '<td>' . $fk->COLUMN_NAME . '</td>';
        echo '<td>' . $fk->REFERENCED_TABLE_NAME . '(' . $fk->REFERENCED_COLUMN_NAME . ')</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<h2>Fix Action</h2>';
    
    // Try to drop the foreign key
    $constraint_name = $fks[0]->CONSTRAINT_NAME;
    
    $drop_result = $wpdb->query("ALTER TABLE $table_name DROP FOREIGN KEY $constraint_name");
    
    if ($drop_result !== false) {
        echo '<p style="color: green;"><strong>✓ Foreign key constraint dropped successfully!</strong></p>';
        
        // Verify it's gone
        $verify_fks = $wpdb->get_results("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = '" . str_replace($wpdb->prefix, '', $table_name) . "'
            AND TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        if (empty($verify_fks)) {
            echo '<p style="color: green;"><strong>✓ Verified: No foreign keys remain</strong></p>';
        } else {
            echo '<p style="color: orange;">⚠️ Warning: Foreign keys still exist</p>';
        }
    } else {
        echo '<p style="color: red;"><strong>✗ Error dropping foreign key:</strong></p>';
        echo '<pre style="background: #f8d7da; padding: 10px;">' . $wpdb->last_error . '</pre>';
    }
}

echo '<hr>';
echo '<p><em>Fix completed at ' . current_time('mysql') . '</em></p>';
?>
