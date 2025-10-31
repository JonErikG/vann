# 🚀 Quick Start Guide - Orkla Water Level Plugin v1.0.8

## ✅ What's Fixed

**The graphs now work!** Version 1.0.8 fixes the Chart.js loading issues that prevented graphs from displaying.

---

## 📥 Installation (New Install)

1. **Upload Plugin**
   ```
   - Upload orkla-water-level folder to /wp-content/plugins/
   - Activate via WordPress admin
   ```

2. **Import Data**
   ```
   - Go to: Water Level → Debug Status
   - Click: "Run Full Historical Import"
   - Wait for completion
   ```

3. **Add to Page**
   ```
   - Edit any page/post
   - Add shortcode: [orkla_water_level]
   - Publish and view
   ```

4. **Verify**
   ```
   - Graphs should display
   - Press F12 to check console
   - Should see: ✓ Chart.js loaded successfully
   ```

---

## 🔄 Upgrade (From v1.0.7 or earlier)

### Option A: Quick Upgrade (3 steps)

1. **Replace Files**
   - Backup current plugin folder (optional)
   - Upload new v1.0.8 files to replace old ones
   - Verify vendor directory exists with Chart.js files

2. **Clear Cache**
   - Browser: Press Ctrl+Shift+R
   - WordPress: Clear from caching plugin
   - Server: Clear from hosting panel

3. **Test**
   - Visit admin dashboard
   - Verify graphs display
   - Check browser console (F12) for errors

### Option B: Safe Upgrade (with verification)

1. **Backup**
   ```bash
   cd /wp-content/plugins/
   cp -r orkla-water-level orkla-water-level.backup
   ```

2. **Upload v1.0.8**
   - Upload entire orkla-water-level folder
   - Overwrite all files

3. **Verify Files**
   Check these files exist:
   ```
   orkla-water-level/assets/js/vendor/chart.min.js (195 KB)
   orkla-water-level/assets/js/vendor/chartjs-adapter-date-fns.bundle.min.js (50 KB)
   ```

4. **Clear All Caches**
   - Browser cache (Ctrl+Shift+R)
   - WordPress cache
   - Server cache
   - CDN cache (if applicable)

5. **Run Health Check**
   - Upload test-plugin-health.php to WordPress root
   - Access: https://yoursite.com/test-plugin-health.php
   - Review all tests
   - **Delete file after testing!**

6. **Verify Success**
   - Admin dashboard shows charts ✓
   - Frontend widgets show charts ✓
   - Console shows "Chart.js loaded" ✓
   - No JavaScript errors ✓

---

## 🔍 Quick Troubleshooting

### Graphs Not Showing?

**Step 1: Hard Refresh**
```
Windows/Linux: Ctrl+Shift+R
Mac: Cmd+Shift+R
```

**Step 2: Check Console**
```
Press F12
Look for errors
Should see: ✓ Chart.js loaded successfully
```

**Step 3: Verify Files**
```
Check: /assets/js/vendor/chart.min.js exists
Check: File is 195 KB
Check: File is readable (chmod 644)
```

**Step 4: Run Health Check**
```
Upload test-plugin-health.php
Access via browser
Review failed tests
Delete file when done
```

### No Data Available?

```
Go to: Water Level → Debug Status
Click: "Run Full Historical Import"
Wait: Import completes
Verify: Total records > 0
```

### AJAX Errors?

```
Check: Plugin is activated
Check: WordPress version (5.0+ required)
Check: PHP version (7.4+ required)
Review: wp-content/debug.log
```

---

## 📚 Documentation

- **README.md** - Full feature list and documentation
- **CHANGELOG.md** - Complete version history
- **UPGRADE-GUIDE.md** - Detailed upgrade instructions
- **TROUBLESHOOTING.md** - Comprehensive troubleshooting
- **QUICK-FIX.md** - Common issues and solutions
- **GRAPH-NOT-SHOWING.md** - Graph-specific issues

---

## 🎯 Success Checklist

After installation/upgrade, verify:

- [ ] Plugin is activated
- [ ] Database has data (check Debug Status page)
- [ ] Admin dashboard displays charts
- [ ] Frontend widgets display charts
- [ ] Browser console shows no errors
- [ ] Console shows "✓ Chart.js loaded successfully"
- [ ] Period selector works (today, week, month, year)
- [ ] AJAX requests complete successfully
- [ ] Health check passes (if you ran it)

---

## 💡 Pro Tips

1. **Cache Issues**
   - After ANY plugin update, do a hard refresh (Ctrl+Shift+R)
   - Clear WordPress cache after updating plugin files

2. **File Permissions**
   - If "permission denied" errors, check file permissions
   - Files: 644, Directories: 755

3. **Ad Blockers**
   - v1.0.8 works WITH ad blockers (local Chart.js)
   - No need to disable ad blockers anymore

4. **Health Check**
   - Keep test-plugin-health.php handy for troubleshooting
   - Always delete it after use (security)

5. **Import Data**
   - Run "Full Historical Import" once after installation
   - Hourly cron will keep it updated automatically

---

## 🆘 Need Help?

**Before asking for help, gather this info:**

1. Run test-plugin-health.php and screenshot results
2. Open browser console (F12) and screenshot errors
3. Check WordPress debug.log for PHP errors
4. Note your WordPress version
5. Note your PHP version
6. List your active theme
7. List your active plugins

**Provide this when asking for help to get faster support!**

---

## 📊 What Changed in v1.0.8

- ✅ Chart.js now bundled locally (no CDN!)
- ✅ Fixed script loading order
- ✅ Removed duplicate code
- ✅ Enhanced error messages
- ✅ Added health check tool
- ✅ Better console logging
- ✅ Cache busting system

**Result:** Graphs work reliably everywhere!

---

## 🎉 Expected Results

### Admin Dashboard
```
✓ Shows water level chart
✓ Shows statistics (current, avg, max, min)
✓ Period selector works
✓ Refresh button works
✓ No JavaScript errors
```

### Frontend Widget
```
✓ Shows water level chart
✓ Shows multiple data series
✓ Interactive tooltips
✓ Responsive design
✓ No JavaScript errors
```

### Browser Console
```
✓ Chart.js loaded successfully (version 3.9.1)
✓ AJAX response received: {...}
✓ Data received: X records
✓ First record: {...}
✓ Chart rendered successfully
```

---

## 🔐 Security Note

**test-plugin-health.php should be deleted after use!**

This file is for diagnostics only and should NOT remain on your server.

```bash
# Delete it manually or via FTP
rm test-plugin-health.php
```

---

## ⏰ What's Automatic

These things happen automatically:
- ✓ Hourly CSV data import (via cron)
- ✓ Database updates
- ✓ Cache busting (version-based)
- ✓ Error logging (if WP_DEBUG enabled)

---

## 📞 Support Resources

1. **Read Documentation**
   - Start with README.md
   - Check TROUBLESHOOTING.md
   - Review UPGRADE-GUIDE.md

2. **Run Diagnostics**
   - Use test-plugin-health.php
   - Check browser console (F12)
   - Review debug.log

3. **Search Issues**
   - Check existing troubleshooting docs
   - Review GRAPH-NOT-SHOWING.md
   - Review QUICK-FIX.md

---

**Plugin Version:** 1.0.8
**Last Updated:** 2025-10-31
**Status:** ✅ Production Ready

---

Happy charting! 📈
