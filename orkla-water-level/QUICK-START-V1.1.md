# Quick Start Guide - Orkla Water Level Plugin v1.1.0

## What's New in v1.1.0?

Version 1.1.0 adds powerful monitoring and optimization features to help you maintain a healthy, high-performance water level monitoring system.

## 🚀 Getting Started with New Features

### 1. Access the Health Monitor (2 minutes)

1. Log in to WordPress Admin
2. Navigate to **Water Level → Health Monitor**
3. You'll immediately see:
   - Overall system health status (Green = Good, Yellow = Warning, Red = Critical)
   - Individual health checks for database, data freshness, import status, and more
   - Comprehensive statistics about your data
   - Any detected data gaps

**What to look for:**
- ✅ Green status = Everything is working perfectly
- ⚠️ Yellow status = Minor issues that should be addressed
- ❌ Red status = Critical issues requiring immediate attention

### 2. Understanding Your Data Quality (5 minutes)

The Health Monitor shows you:

#### System Health Checks
- **Database Health:** Verifies your database table is working correctly
- **Data Freshness:** Alerts if your data is outdated (>3 hours is yellow, >24 hours is red)
- **Data Quality:** Checks for null values, outliers, and duplicates
- **Import Status:** Shows when the last import ran and if there were errors
- **Cron Status:** Confirms your hourly data fetch is scheduled

#### Data Statistics
- Total records in your database
- Records imported in the last 24 hours
- Date range of your data (earliest to latest)
- Field coverage (what percentage of records have data for each field)
- Average, min, and max water levels (last 7 days)

#### Data Gaps
- Shows any periods longer than 2 hours where data is missing
- Helps identify when your data source was unavailable

### 3. Running Your First Health Check (1 minute)

1. Click the **"Refresh Health Check"** button
2. The page will reload with the latest system status
3. Review any warnings or errors
4. Take action on any issues found

**When to run health checks:**
- After plugin updates
- When troubleshooting issues
- Weekly as routine maintenance
- Before important events or presentations

### 4. Cleaning Up Old Data (3 minutes)

Over time, your database will grow. Clean up old data to maintain performance:

1. Go to **Water Level → Health Monitor**
2. Scroll to the **"Maintenance"** section
3. Select retention period:
   - **1 year** - Keep recent data only (recommended for most users)
   - **2 years** - Keep more historical data
   - **3 years** - Maximum historical retention
4. Click **"Cleanup Old Data"**
5. Confirm the operation
6. Wait for completion (you'll see a success message)

**Benefits of cleanup:**
- Faster database queries
- Reduced backup sizes
- Better overall performance
- Optimized storage usage

**Recommendation:** Run cleanup every 3-6 months, or when you notice performance issues.

## 📊 Understanding the Dashboard

### Health Status Colors

#### 🟢 Green (Healthy/OK)
Everything is working as expected. No action needed.

#### 🟡 Yellow (Warning)
Minor issues detected, but the system is still functional. Review and address when convenient.

**Common yellow warnings:**
- Data is 3-6 hours old (import may be delayed)
- Some records have null values (normal if data source occasionally lacks measurements)
- A few data gaps detected (occasional network issues)

#### 🔴 Red (Critical/Error)
Serious issues that need immediate attention.

**Common red errors:**
- Data is more than 24 hours old (import not running)
- Database table doesn't exist (plugin needs reactivation)
- Cron job not scheduled (automatic updates not working)
- Last import had errors (check WordPress error log)

### Reading the Statistics

#### Total Records
Total number of hourly measurements in your database.
- **Expected:** Roughly 24 records per day × days of operation
- **Example:** 30 days × 24 hours = ~720 records

#### Records Last 24h
How many records were imported in the last day.
- **Expected:** Around 24 (one per hour)
- **Less than 20:** Possible import issues or gaps
- **More than 30:** Possible duplicate imports (check data quality)

#### Field Coverage
Shows what percentage of your records have data for each field:
- **100%:** Perfect, every record has this data
- **90-99%:** Excellent, occasional missing values
- **80-89%:** Good, some gaps in data source
- **<80%:** Review data source configuration

## 🔧 Troubleshooting with Health Monitor

### Problem: Red status showing "Data is stale"

**Cause:** Import process not running or failing

**Solution:**
1. Go to **Water Level → Dashboard**
2. Click "Run CSV Import" manually
3. If errors appear, check:
   - CSV file URL is accessible
   - WordPress cron is working
   - No PHP errors in debug.log

### Problem: Yellow status showing data quality issues

**Cause:** Some records have unexpected values

**Solution:**
1. Review the specific issues listed
2. Check if the data source is having problems
3. Most quality issues are minor and don't affect functionality
4. If outliers persist, review CSV parsing configuration

### Problem: Import showing 0 imported, 1 updated, 1 skipped

**Cause:** This is normal incremental import behavior

**Context:**
- The import system only processes NEW data
- "Updated" means it refreshed an existing record
- "Skipped" means no new measurements were available
- This is efficient and working correctly

**Solution:** No action needed - system is working as designed

### Problem: Cron status shows "Not scheduled"

**Cause:** WordPress cron job was cleared or plugin was deactivated

**Solution:**
1. Deactivate the plugin
2. Reactivate the plugin
3. This will reschedule the hourly cron job
4. Verify in Health Monitor that status is now "OK"

## 📈 Best Practices

### Daily Tasks
- None required! The system runs automatically.

### Weekly Tasks
1. Quick glance at Health Monitor dashboard (30 seconds)
2. Verify overall status is green
3. Check that recent data exists

### Monthly Tasks
1. Run full health check (1 minute)
2. Review data statistics trends
3. Check for data gaps
4. Note any recurring warnings

### Quarterly Tasks
1. Run data cleanup for old records (3 minutes)
2. Review overall data quality trends
3. Optimize database if needed

### Annual Tasks
1. Review retention policy (do you need data older than 1 year?)
2. Consider exporting historical data for archival
3. Audit field coverage and data quality

## 🎯 Performance Tips

### Optimize Database Performance
1. Run cleanup regularly (quarterly recommended)
2. Keep only data you need
3. Use the Health Monitor to detect issues early

### Reduce Import Overhead
- The new smart import system automatically:
  - Only processes new/changed data
  - Validates data before inserting
  - Uses efficient batch processing
  - Skips unnecessary database operations

### Monitor Proactively
- Check Health Monitor weekly
- Address yellow warnings before they become red errors
- Keep WordPress and PHP updated
- Maintain adequate server resources

## 🆘 Getting Help

### 1. Check Health Monitor First
Most issues are immediately visible in the Health Monitor with:
- Clear status indicators
- Specific error messages
- Actionable recommendations

### 2. Review Documentation
- `IMPROVEMENTS.md` - Detailed technical documentation
- `TROUBLESHOOTING.md` - Common issues and solutions
- `README.md` - General plugin information

### 3. Check Logs
WordPress debug log (`wp-content/debug.log`) contains:
- Import execution logs
- CSV download status
- Database operation results
- Error details with timestamps

### 4. Use Debug Tools
- **Debug Status** page - Shows database contents and import history
- **Test Plugin Health** script - Standalone diagnostic tool
- **CSV Source Diagnostics** - Verifies CSV file accessibility

## 📝 Quick Reference

### New Admin Pages
- **Water Level → Health Monitor** - System health dashboard

### New Features
- Real-time health monitoring
- Data quality checking
- Performance statistics
- Data gap detection
- Old data cleanup
- Database optimization

### New AJAX Endpoints
- `orkla_run_health_check` - Refresh health status
- `orkla_cleanup_old_data` - Remove old records

### New Classes
- `Orkla_Health_Monitor` - Monitoring functionality
- `Orkla_Import_Optimizer` - Performance optimization

## 🎓 Advanced Usage

### Customizing Health Checks

Add custom checks via filters:

```php
add_filter('orkla_health_checks', function($checks) {
    // Add your custom health check
    return $checks;
});
```

### Adjusting Import Strategy

Change lookback period for imports:

```php
add_filter('orkla_import_lookback_hours', function() {
    return 4; // Look back 4 hours instead of default 2
});
```

### Scheduling Automatic Cleanup

Use WordPress cron to run cleanup monthly:

```php
add_action('wp', function() {
    if (!wp_next_scheduled('orkla_monthly_cleanup')) {
        wp_schedule_event(time(), 'monthly', 'orkla_monthly_cleanup');
    }
});

add_action('orkla_monthly_cleanup', function() {
    $optimizer = new Orkla_Import_Optimizer();
    $optimizer->cleanup_old_data(365);
});
```

## ✅ Success Checklist

After installing v1.1.0, verify:

- [ ] Health Monitor page is accessible
- [ ] Overall status shows as green or yellow (red means action needed)
- [ ] All health checks are passing or have minor warnings
- [ ] Data statistics look reasonable
- [ ] Can click "Refresh Health Check" successfully
- [ ] Cleanup functionality works (test with long retention period first)
- [ ] Existing graphs and widgets still display correctly
- [ ] Hourly cron job is scheduled

## 🎉 You're Ready!

Your Orkla Water Level Plugin is now enhanced with:
- ✅ Proactive health monitoring
- ✅ Performance optimization
- ✅ Better data management
- ✅ Easier troubleshooting

Monitor your system regularly, keep it clean, and enjoy reliable water level tracking!
