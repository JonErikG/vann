# Orkla Water Level Plugin - Troubleshooting Guide

## Issue: Graph Not Showing Data

If your water level graphs are not displaying, follow these steps:

### Step 1: Check the Debug Status Page

1. Go to **WordPress Admin**
2. Navigate to **Water Level > Debug Status**
3. Check the "Total records" count

### Step 2: Understanding the Problem

The plugin has two import modes:

1. **Incremental Import (Default)** - Only imports data NEWER than what's already in the database
2. **Full Historical Import** - Imports ALL data from the CSV file

**The Issue:** When you click "Run CSV Import", it only imports new data. If the CSV contains 45 records but your database already has recent data, it will skip most records.

**From your logs:**
```
Parsed 45 values for field: water_level_1
Import cutoff timestamp: 2025-10-31 19:00:00
Combined 2 records for import
Import completed - Imported: 0, Updated: 1, Skipped: 1
```

This shows:
- ✓ CSV parsed successfully (45 values)
- ✗ Only 2 records newer than cutoff
- ✗ Result: 0 new records, 1 updated, 1 skipped

### Step 3: Solution - Run Full Historical Import

#### Option A: Via Debug Status Page
1. Go to **Water Level > Debug Status**
2. If you see "⚠ No data in database!"
3. Click **"Run Full Historical Import"** button
4. Wait for completion
5. Refresh the page to verify data was imported

#### Option B: Via Dashboard
1. Go to **Water Level > Dashboard**
2. Click the red **"Full Historical Import"** button
3. Confirm the dialog prompt
4. Wait for the import to complete
5. Check the summary message

### Step 4: Verify the Fix

After running the Full Historical Import:

1. Check the import summary shows imported records > 0
2. Go to **Debug Status** page and verify:
   - Total records > 0
   - Latest timestamp is shown
   - Data range is displayed
3. Click **"Test AJAX Request"** button to verify data fetching works
4. Visit your page with the `[orkla_water_level]` shortcode
5. Graph should now display properly

### Step 5: Understanding Future Imports

After the initial full import:

- **Hourly Cron Job** - Automatically imports new data every hour
- **"Run CSV Import"** button - Manually imports only NEW data
- **"Full Historical Import"** button - Reimports ALL data (use sparingly)

## Common Issues

### Issue: Still No Data After Full Import

**Check:**
1. Browser console (F12) for JavaScript errors
2. Verify Chart.js is loading: Look for `chart.min.js` in Network tab
3. Check that shortcode is correct: `[orkla_water_level]`

### Issue: CSV Not Being Downloaded

**Check:**
1. Go to **Dashboard > Diagnose CSV Sources**
2. Verify the CSV URL is accessible: https://orklavannstand.online/VannforingOrkla.csv
3. Check the CSV file exists in: `/wp-content/uploads/orkla-water-level/`

### Issue: Import Shows Errors

**Check:**
1. Look at the error log for detailed messages
2. Go to **Dashboard** and check the "Last CSV import" notice
3. Errors will be listed in red

## Debug Mode

The plugin has debug mode enabled by default (`DEBUG_MODE = true`).

This adds extensive logging to help diagnose issues:
- CSV download status
- Parsing results with record counts
- Import cutoff timestamps
- Database import results
- AJAX request details

Check your WordPress debug log for these messages.

## Support Files

### test-database-status.php
A standalone diagnostic tool that shows:
- Database table status
- Record counts
- Latest records
- CSV file status
- AJAX endpoint testing

**To use:**
1. Upload `test-database-status.php` to your WordPress root
2. Access it at: `https://yoursite.com/test-database-status.php`
3. **Delete it after use for security**

## Summary

**The main issue:** The regular CSV import only imports NEW data. You need to run a **Full Historical Import** once to populate the database with all historical data from the CSV file.

**Quick Fix:**
1. Go to **Water Level > Debug Status**
2. Click **"Run Full Historical Import"**
3. Wait for completion
4. Verify graphs are now showing data

After this, the hourly cron job will keep your data updated automatically.
