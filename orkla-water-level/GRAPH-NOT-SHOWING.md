# Graph Not Showing - Diagnostic Guide

## Current Status

✅ **Database**: 91,340 records loaded successfully
✅ **AJAX Endpoint**: Returning 20 records for "today"
✅ **CSV Import**: Working correctly (Full Historical Import successful)
❌ **Frontend**: Graph not displaying

## Diagnostic Steps

### Step 1: Upload Test File

1. Upload `test-frontend.php` to your WordPress root directory
2. Access it at: `https://orklavannstand.online/test-frontend.php`
3. The page will automatically:
   - Check if Chart.js is loaded
   - Test the AJAX endpoint
   - Show sample data
   - Attempt to render a test chart

### Step 2: Check Browser Console

Open your browser's developer console (Press F12) and look for:

**Expected Console Output:**
```
Orkla Frontend JS loaded
AJAX URL: https://yoursite.com/wp-admin/admin-ajax.php
Nonce: xxxxxxxxxx
Initializing water level widget
Loading water level data for period: today
```

**Common Errors to Look For:**
- ❌ `orkla_ajax is not defined` - Scripts not loading correctly
- ❌ `Chart is not defined` - Chart.js not loaded
- ❌ `$ is not defined` - jQuery not loaded
- ❌ `AJAX request failed` - AJAX endpoint issue
- ❌ `JSON parse error` - Response format issue

### Step 3: Verify Page Setup

**Check your page contains:**
```
[orkla_water_level]
```

**Common Issues:**
- Wrong shortcode name (should be `orkla_water_level`)
- Extra spaces or special characters in shortcode
- Page builder caching the old version

### Step 4: Check for Plugin Conflicts

**Test for conflicts:**
1. Temporarily disable other plugins one by one
2. Check if graph appears
3. Common conflicting plugins:
   - Page builder plugins
   - Caching plugins
   - Other Chart.js plugins
   - jQuery optimization plugins

### Step 5: Theme Issues

**Test with default theme:**
1. Switch to a default WordPress theme (Twenty Twenty-Four)
2. Check if graph appears
3. If it works, your theme has a conflict

## Quick Fixes to Try

### Fix 1: Clear All Caches
1. WordPress cache
2. Browser cache (Ctrl+Shift+R)
3. CDN cache (if using)
4. Server cache

### Fix 2: Force Script Reload
Add this to your theme's `functions.php` temporarily:
```php
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_script('orkla-frontend');
    wp_deregister_script('orkla-frontend');
}, 100);
```

### Fix 3: Check jQuery Version
The plugin requires jQuery. Some themes don't load it properly.

Add to `functions.php`:
```php
add_action('wp_enqueue_scripts', function() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
});
```

### Fix 4: Verify Shortcode is Processing
Add this test to confirm shortcode is working:
```
[orkla_water_level]

If you see this text, the shortcode is not being processed.
```

## Using test-frontend.php

This standalone test page bypasses WordPress theme/plugins and tests:

1. **Chart.js Loading** - Confirms Chart.js library loads
2. **AJAX Connection** - Tests direct connection to data endpoint
3. **Data Format** - Shows exactly what data is returned
4. **Chart Rendering** - Tests if charts can render with your data

**What to Check:**
- ✅ Test 1 should show "Chart.js loaded successfully"
- ✅ Test 2 should return 20 records with data
- ✅ Test 3 chart should render successfully
- ❌ If any test fails, note the error message

## Expected Log Output

After Full Historical Import, your logs should show:
```
[Date Time] Orkla Plugin: Import completed - Imported: 0, Updated: 44, Skipped: 1
[Date Time] Orkla Plugin: Total rows in database: 91340
[Date Time] Orkla Plugin: Returning 20 records for period: today
[Date Time] Orkla Plugin: First timestamp: 2025-10-31 00:00:00
[Date Time] Orkla Plugin: Last timestamp: 2025-10-31 19:00:00
[Date Time] Orkla Plugin: Sample record: {"timestamp":"...","vannforing_brattset":"50.7",...}
```

## Next Steps Based on Results

### If test-frontend.php Works
**Issue**: Theme or plugin conflict
**Solution**:
1. Switch themes to test
2. Disable plugins one by one
3. Check page builder settings

### If AJAX Returns No Data
**Issue**: Database or query problem
**Solution**:
1. Go to Debug Status page
2. Verify database has records
3. Check error logs for query errors

### If Chart.js Not Loading
**Issue**: CDN blocked or script conflict
**Solution**:
1. Check network tab (F12) for failed requests
2. Look for Chart.js 404 or blocked
3. Try different CDN or download locally

### If JavaScript Errors
**Issue**: Code conflict or version mismatch
**Solution**:
1. Note exact error message
2. Check which file has the error
3. Look for jQuery or Chart.js version conflicts

## Support Information

**What to Provide if Asking for Help:**

1. Results from `test-frontend.php`
2. Browser console output (F12)
3. Network tab showing AJAX request/response
4. Active theme name
5. List of active plugins
6. Any error messages from logs

## Files to Check

- `test-frontend.php` - Comprehensive frontend test
- `test-database-status.php` - Database verification
- WordPress debug log - PHP errors
- Browser console - JavaScript errors

## Summary

Your backend is working perfectly:
- ✅ Database populated with 91,340 records
- ✅ AJAX endpoint returns data correctly
- ✅ Full Historical Import successful

The issue is on the frontend display. Use `test-frontend.php` to identify the exact problem.
