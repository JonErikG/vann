# Upgrade Guide to Version 1.0.8

## What's New

Version 1.0.8 fixes the **"Graph Not Displaying"** issue that was preventing charts from rendering in both the admin dashboard and frontend widgets.

## The Problem (v1.0.7 and earlier)

Charts were not displaying because:
- Chart.js library loaded from CDN (cdn.jsdelivr.net)
- CDN was being blocked by:
  - Hosting provider firewalls
  - Ad blockers
  - Network restrictions
  - Corporate firewalls
- Script loading order issues
- Duplicate script localizations causing conflicts

## The Solution (v1.0.8)

✅ **Chart.js is now bundled locally** - No more CDN dependencies
✅ **Fixed script loading order** - Proper dependency chains
✅ **Removed duplicate code** - Eliminated script conflicts
✅ **Enhanced error handling** - Clear troubleshooting messages
✅ **Cache busting** - Version-based file loading

## How to Upgrade

### Step 1: Backup (Recommended)
```bash
# Backup your current plugin folder
cp -r wp-content/plugins/orkla-water-level wp-content/plugins/orkla-water-level.backup
```

### Step 2: Upload New Files
1. Download the v1.0.8 plugin files
2. Replace the entire `orkla-water-level` folder in `wp-content/plugins/`
3. Make sure these new files are present:
   - `assets/js/vendor/chart.min.js`
   - `assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js`

### Step 3: Clear All Caches
```
Browser Cache: Ctrl+Shift+R (hard refresh)
WordPress Cache: Clear from your caching plugin
Server Cache: Clear from your hosting control panel
CDN Cache: Purge if using Cloudflare/similar
```

### Step 4: Verify the Upgrade

#### Quick Check:
1. Go to admin dashboard: Water Level → Dashboard
2. You should see charts displaying
3. Open browser console (F12)
4. Look for: `✓ Chart.js loaded successfully (version 3.9.1)`

#### Full Health Check:
1. Upload `test-plugin-health.php` to WordPress root
2. Access: `https://yoursite.com/test-plugin-health.php`
3. Review all test results
4. **Delete the file after testing** (security)

### Step 5: Test Frontend
1. Visit a page with `[orkla_water_level]` shortcode
2. Verify charts are displaying
3. Try different time periods (today, week, month, year)
4. Check browser console for errors

## Troubleshooting

### Issue: Graphs Still Not Showing

**Solution 1: Hard Refresh**
```
Press: Ctrl+Shift+R (Windows/Linux)
Press: Cmd+Shift+R (Mac)
```

**Solution 2: Clear Browser Data**
1. Open DevTools (F12)
2. Right-click the refresh button
3. Select "Empty Cache and Hard Reload"

**Solution 3: Check File Permissions**
```bash
# Make sure vendor files are readable
chmod 644 wp-content/plugins/orkla-water-level/assets/js/vendor/*.js
```

**Solution 4: Run Health Check**
Upload and run `test-plugin-health.php` to diagnose the issue.

### Issue: "Chart.js is NOT loaded" Error

This should NOT happen in v1.0.8, but if it does:

1. **Verify files exist:**
   ```bash
   ls -lh wp-content/plugins/orkla-water-level/assets/js/vendor/
   # Should show:
   # chart.min.js (195 KB)
   # chartjs-adapter-date-fns.bundle.min.js (50 KB)
   ```

2. **Check file permissions:**
   ```bash
   # Files should be readable by web server
   chmod 644 wp-content/plugins/orkla-water-level/assets/js/vendor/*.js
   ```

3. **Verify in browser:**
   - Open DevTools (F12)
   - Go to Network tab
   - Refresh page
   - Look for `chart.min.js` - should load with status 200

### Issue: No Data Available

This is a separate issue from v1.0.8 fixes. To resolve:

1. Go to: Water Level → Debug Status
2. Check "Total records in database"
3. If 0 records, click "Run Full Historical Import"
4. Wait for import to complete
5. Refresh the page

### Issue: AJAX Errors

Check these:
1. **Plugin activated:** Plugins → Installed Plugins
2. **WordPress version:** Requires WP 5.0+
3. **PHP version:** Check in Site Health
4. **Debug.log:** Check `wp-content/debug.log` for errors

## What Changed

### Files Modified:
- `orkla-water-level.php` - Core plugin logic
- `assets/js/frontend.js` - Frontend JavaScript
- `assets/js/admin.js` - Admin JavaScript
- `README.md` - Documentation

### Files Added:
- `assets/js/vendor/chart.min.js` - Chart.js library
- `assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js` - Date adapter
- `test-plugin-health.php` - Diagnostic tool
- `CHANGELOG.md` - Version history
- `UPGRADE-GUIDE.md` - This file

### Files Unchanged:
- Database tables (no migration needed)
- Templates
- CSS files
- Settings/configuration

## Rollback Instructions

If you need to rollback to v1.0.7:

```bash
# Remove v1.0.8
rm -rf wp-content/plugins/orkla-water-level

# Restore backup
cp -r wp-content/plugins/orkla-water-level.backup wp-content/plugins/orkla-water-level

# Clear cache
# (Clear browser and WordPress cache)
```

**Note:** Rollback should not be necessary. If you experience issues, please troubleshoot first.

## Benefits of v1.0.8

✅ **Reliability:** No CDN dependencies = works everywhere
✅ **Performance:** Local files load faster than CDN
✅ **Security:** No external dependencies = better security
✅ **Compatibility:** Works behind any firewall/ad blocker
✅ **Maintenance:** Version-based cache busting = easier updates

## FAQ

### Q: Do I need to reconfigure anything?
**A:** No, all settings are preserved. Just upload and go.

### Q: Will this affect my existing data?
**A:** No, database tables and data are unchanged.

### Q: Do I need to reimport my CSV data?
**A:** No, existing data remains intact.

### Q: What if I use a CDN for my site?
**A:** v1.0.8 works perfectly with site-level CDNs. We only removed the Chart.js CDN dependency.

### Q: Can I still use the CDN version of Chart.js?
**A:** Yes! Edit `orkla-water-level.php` line ~241 and set `$use_local_chartjs = false;`

### Q: Is this compatible with my theme/plugins?
**A:** Yes, v1.0.8 maintains full compatibility with all themes and plugins.

### Q: How large is the plugin now?
**A:** Added ~245 KB for Chart.js libraries. Negligible impact on site performance.

## Support

If you need help:

1. **Read the docs:**
   - `README.md` - Overview and features
   - `TROUBLESHOOTING.md` - Detailed troubleshooting
   - `QUICK-FIX.md` - Common quick fixes
   - `GRAPH-NOT-SHOWING.md` - Graph issues specifically

2. **Run diagnostics:**
   - Upload and run `test-plugin-health.php`
   - Check browser console (F12)
   - Review WordPress debug.log

3. **Gather information:**
   - Health check results
   - Browser console output
   - WordPress version
   - Theme name
   - Active plugins list

## Success Checklist

After upgrading, you should see:

- [x] Admin dashboard displays charts
- [x] Frontend widgets display charts
- [x] No JavaScript errors in console
- [x] Console shows: `✓ Chart.js loaded successfully (version 3.9.1)`
- [x] AJAX requests return data successfully
- [x] Period selector changes work smoothly
- [x] Health check passes all tests

If all items are checked, your upgrade is complete! 🎉

## Version History

- **v1.0.8** (2025-10-31) - Fixed graph display issues
- **v1.0.7** (Previous) - Full historical import feature
- **v1.0.0** (Initial) - First release

---

**Last Updated:** 2025-10-31
**Plugin Version:** 1.0.8
**Requires:** WordPress 5.0+, PHP 7.4+
