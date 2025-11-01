# Restoring Backend and Frontend Functionality

This guide will help you restore the CSV auto-import and graph display functionality for the Orkla Water Level plugin.

## Quick Start - Run Diagnostic Scripts

Two diagnostic scripts have been created to help you identify and fix issues:

### 1. Diagnostic Check Script

**Purpose:** Identifies current system state and potential issues

**Location:** `orkla-water-level/diagnostic-check.php`

**How to use:**
1. Upload `diagnostic-check.php` to your WordPress server in the plugin directory
2. Access it via browser: `https://yoursite.com/wp-content/plugins/orkla-water-level/diagnostic-check.php`
3. Review the diagnostic results

**What it checks:**
- ✅ Database table exists and has records
- ✅ Latest data timestamp (checks if data is stale)
- ✅ Cron job is scheduled for hourly imports
- ✅ CSV data source URL is accessible
- ✅ CSV cache directory exists and is writable
- ✅ Chart.js and date adapter libraries are present
- ✅ Plugin is active

### 2. Fix and Test Script

**Purpose:** Automatically fixes common issues and runs functionality tests

**Location:** `orkla-water-level/fix-and-test.php`

**How to use:**
1. Upload `fix-and-test.php` to your WordPress server in the plugin directory
2. Access it via browser: `https://yoursite.com/wp-content/plugins/orkla-water-level/fix-and-test.php`
3. The script will automatically attempt to fix issues

**What it fixes:**
- 🔧 Creates CSV cache directory if missing
- 🔧 Sets correct permissions on cache directory
- 🔧 Reschedules cron job for hourly CSV imports
- 🔧 Verifies database table structure
- 🔧 Tests CSV URL accessibility
- 🔧 Runs a manual CSV import to populate data
- 🔧 Tests AJAX endpoint functionality
- 🔧 Verifies Chart.js files are present

## Understanding the System

### Backend: CSV Auto-Import

**How it works:**
1. WordPress cron runs the `orkla_fetch_data_hourly` action every hour
2. The `fetch_csv_data()` function downloads CSV from `https://orklavannstand.online/VannforingOrkla.csv`
3. CSV is cached in `wp-content/uploads/orkla-water-level/` directory
4. Data is parsed and imported into `wp_orkla_water_data` table
5. Smart cutoff prevents duplicate imports (only imports last 2 hours by default)

**Common issues:**
- ❌ **Cron not scheduled**: Plugin needs reactivation
- ❌ **Cache directory missing**: Needs to be created with write permissions
- ❌ **CSV URL unreachable**: Server firewall or network issue
- ❌ **Data not importing**: Check WordPress error logs for details

### Frontend: Graph Display

**How it works:**
1. User visits page with `[orkla_water_display]` shortcode
2. Template loads Chart.js libraries from `assets/js/vendor/`
3. JavaScript makes AJAX call to `get_water_data` endpoint
4. Backend queries WordPress database and transforms field names
5. JavaScript receives data and renders Chart.js graph

**Common issues:**
- ❌ **Chart.js not loaded**: Files missing or blocked by ad blocker
- ❌ **No data to display**: Database is empty, run CSV import
- ❌ **AJAX errors**: Check nonce verification and permissions
- ❌ **Graph not rendering**: Check browser console for JavaScript errors

## Manual Troubleshooting Steps

### Step 1: Verify Plugin is Active

```bash
wp plugin list --status=active
```

Look for `orkla-water-level` in the list. If not active:

```bash
wp plugin activate orkla-water-level
```

### Step 2: Check Database Table

```sql
SHOW TABLES LIKE 'wp_orkla_water_data';
SELECT COUNT(*) FROM wp_orkla_water_data;
SELECT MAX(timestamp) FROM wp_orkla_water_data;
```

If table doesn't exist, reactivate the plugin.

### Step 3: Verify Cron Schedule

```bash
wp cron event list
```

Look for `orkla_fetch_data_hourly`. If not scheduled:

```php
// In WordPress admin, go to Plugins and reactivate the plugin
// OR run this PHP code:
wp_clear_scheduled_hook('orkla_fetch_data_hourly');
wp_schedule_event(time() + 300, 'hourly', 'orkla_fetch_data_hourly');
```

### Step 4: Test CSV Import Manually

From WordPress admin:
1. Go to **Water Level** → **Dashboard**
2. Click **Run CSV Import** button
3. Check the import summary

### Step 5: Check WordPress Error Log

```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

Look for lines starting with `Orkla Plugin:` for detailed error messages.

### Step 6: Verify Chart.js Files

Check that these files exist:
- `orkla-water-level/assets/js/vendor/chart.min.js` (should be ~195 KB)
- `orkla-water-level/assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js` (should be ~50 KB)

If missing, re-upload the plugin files.

### Step 7: Test Frontend Display

1. Create a new WordPress page
2. Add the shortcode: `[orkla_water_display]`
3. Publish and visit the page
4. Open browser console (F12) and check for errors

## File Permissions

Ensure correct permissions:

```bash
# Plugin directory
chmod 755 /path/to/wordpress/wp-content/plugins/orkla-water-level/

# Cache directory (must be writable)
mkdir -p /path/to/wordpress/wp-content/uploads/orkla-water-level/
chmod 755 /path/to/wordpress/wp-content/uploads/orkla-water-level/

# Plugin files
chmod 644 /path/to/wordpress/wp-content/plugins/orkla-water-level/*.php
chmod 644 /path/to/wordpress/wp-content/plugins/orkla-water-level/assets/js/*.js
```

## Testing the Complete Flow

### Backend Test (CSV Import)

```bash
# Via WP-CLI
wp cron event run orkla_fetch_data_hourly

# Via browser
# Access: /wp-admin/admin.php?page=orkla-water-level&csv_fetch_now=1
```

Expected result:
- CSV is downloaded from remote URL
- Data is parsed (should see ~1000+ lines)
- Records are imported or updated in database
- Import summary shows "Imported: X, Updated: Y, Skipped: Z"

### Frontend Test (Graph Display)

1. Add shortcode to a page: `[orkla_water_display type="both" period="today"]`
2. Visit the page in browser
3. Open browser console (F12)

Expected console output:
```
Orkla Water Level - Frontend initialized
Chart.js version: 3.9.1
Loading data for period: today
Data loaded: X records
Chart rendered successfully
```

Expected display:
- Water flow meters showing current values
- Temperature display
- Interactive Chart.js graph
- Period selector dropdown

## Advanced Configuration

### Change Import Frequency

By default, CSV imports run hourly. To change frequency:

```php
// In your theme's functions.php or a custom plugin
add_filter('cron_schedules', function($schedules) {
    $schedules['every_30_minutes'] = array(
        'interval' => 1800,
        'display'  => 'Every 30 Minutes'
    );
    return $schedules;
});

// Then reschedule
wp_clear_scheduled_hook('orkla_fetch_data_hourly');
wp_schedule_event(time() + 300, 'every_30_minutes', 'orkla_fetch_data_hourly');
```

### Change CSV Source URL

```php
// In your theme's functions.php
add_filter('orkla_shared_remote_csv_url', function($url) {
    return 'https://your-custom-csv-url.com/data.csv';
});
```

### Adjust Import Lookback Period

```php
// Default is 2 hours, change to 4 hours:
add_filter('orkla_import_lookback_hours', function($hours) {
    return 4;
});
```

## Common Error Messages and Solutions

### "Chart.js library not loaded"

**Cause:** Chart.js files are missing or blocked

**Solution:**
1. Verify files exist in `assets/js/vendor/`
2. Disable ad blockers
3. Clear browser cache (Ctrl+Shift+R)
4. Check browser console for 404 errors

### "No data available for this period"

**Cause:** Database is empty or has no data for selected period

**Solution:**
1. Run manual CSV import from admin dashboard
2. Check if cron job is scheduled
3. Verify CSV URL is accessible

### "Security check failed"

**Cause:** Nonce verification failed or user doesn't have permissions

**Solution:**
1. Clear browser cookies and WordPress caches
2. Ensure user is logged in
3. Check if plugin is properly enqueuing scripts

### "Remote CSV request failed with status 403"

**Cause:** Server's IP is blocked or rate-limited by CSV source

**Solution:**
1. Contact CSV source administrator
2. Check server's outgoing firewall rules
3. Verify server can make HTTPS requests

## Performance Optimization

### Database Maintenance

Run cleanup periodically to remove old data:

1. Go to **Water Level** → **Health Monitor**
2. Scroll to **Maintenance** section
3. Select retention period (e.g., "1 year")
4. Click **Cleanup Old Data**

### Optimize Database Table

```sql
OPTIMIZE TABLE wp_orkla_water_data;
```

### Monitor Import Performance

Check import logs:

```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "Orkla Plugin"
```

## Security Considerations

### Delete Diagnostic Scripts

After use, delete these files for security:

```bash
rm /path/to/wordpress/wp-content/plugins/orkla-water-level/diagnostic-check.php
rm /path/to/wordpress/wp-content/plugins/orkla-water-level/fix-and-test.php
```

### Verify Permissions

Ensure only administrators can:
- Access admin dashboard
- Run manual CSV imports
- View health monitor
- Clean up old data

## Getting Help

If issues persist after following this guide:

1. **Run both diagnostic scripts** and save the output
2. **Check WordPress error log** and save relevant entries
3. **Check browser console** and save any errors
4. **Note your environment:**
   - WordPress version
   - PHP version
   - Server type (Apache, Nginx, etc.)
   - Active plugins and theme

Provide this information when seeking help.

## Success Indicators

Your system is working correctly when:

- ✅ Diagnostic check shows all green/passed
- ✅ Database has records less than 3 hours old
- ✅ Cron job is scheduled and running hourly
- ✅ CSV imports complete without errors
- ✅ Graphs display on frontend without JavaScript errors
- ✅ Period selector allows switching between timeframes
- ✅ AJAX requests return data successfully

## Maintenance Schedule

Recommended maintenance tasks:

**Daily:**
- Check Health Monitor dashboard (30 seconds)

**Weekly:**
- Review latest data timestamp
- Verify cron job is running

**Monthly:**
- Run health check
- Review import logs for warnings
- Check database size

**Quarterly:**
- Clean up old data (keep last 1-2 years)
- Review and optimize database tables
- Update plugin if new version available

---

**Last Updated:** 2025-11-01
**Plugin Version:** 1.1.0
**Status:** Production Ready
