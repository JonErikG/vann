# Quick Restore Guide - Orkla Water Level Plugin

**If your CSV auto-import and graphs stopped working, follow these steps:**

---

## 🚀 Quick Fix (5 Minutes)

### Step 1: Upload Fix Script

Upload `fix-and-test.php` to your server:
```
/wp-content/plugins/orkla-water-level/fix-and-test.php
```

### Step 2: Run Fix Script

Access in browser:
```
https://yoursite.com/wp-content/plugins/orkla-water-level/fix-and-test.php
```

The script will automatically:
- ✅ Create/fix cache directory
- ✅ Reschedule cron job
- ✅ Import CSV data
- ✅ Test all functionality

### Step 3: Check Results

Look for these success messages:
- ✅ "CSV import completed successfully"
- ✅ "AJAX endpoint is working"
- ✅ "Chart.js found"
- ✅ "All tests passed!"

### Step 4: Test Frontend

1. Add to a page: `[orkla_water_display]`
2. Visit the page
3. You should see graphs and meters with current data

### Step 5: Clean Up

Delete the fix script (security):
```bash
rm fix-and-test.php
```

**Done! Your system should be working again.**

---

## 🔍 If Problems Persist

### Run Diagnostic Check

1. Upload `diagnostic-check.php`
2. Access: `https://yoursite.com/wp-content/plugins/orkla-water-level/diagnostic-check.php`
3. Review which checks are failing
4. Follow recommendations in the output

### Common Issues

**Issue: "Database table does not exist"**
- Solution: Deactivate and reactivate the plugin

**Issue: "CSV URL unreachable"**
- Solution: Check server firewall settings
- Verify URL works in browser: https://orklavannstand.online/VannforingOrkla.csv

**Issue: "Chart.js NOT found"**
- Solution: Re-upload plugin files (especially `assets/js/vendor/` folder)

**Issue: "Cron job NOT scheduled"**
- Solution: Reactivate the plugin from WordPress admin

**Issue: "No data available"**
- Solution: Go to Water Level → Dashboard → Click "Run CSV Import"

---

## 📖 Detailed Documentation

For complete troubleshooting guide, see:
- `RESTORE-FUNCTIONALITY.md` - Full restoration guide
- `RESTORE-IMPLEMENTATION-SUMMARY.md` - Technical details
- `README.md` - Plugin documentation

---

## 🆘 Emergency Recovery

If everything fails:

1. **Backup current data** (if any)
2. **Deactivate plugin** from WordPress admin
3. **Delete plugin folder** from server
4. **Re-upload fresh plugin files**
5. **Activate plugin** (creates new database tables)
6. **Run fix-and-test.php** to populate data
7. **Test frontend** with shortcode

---

## ✅ Verify System is Working

Your system is healthy when:
- Latest data timestamp is <3 hours old
- Graphs display without errors
- Period selector switches timeframes
- Health Monitor shows green status
- Cron job is scheduled

Check anytime: Water Level → Health Monitor

---

## 📞 Need Help?

When asking for help, provide:
1. Screenshot of diagnostic-check.php output
2. Screenshot of fix-and-test.php output
3. Browser console errors (F12)
4. WordPress version and PHP version

**Keep RESTORE-FUNCTIONALITY.md for complete reference!**

---

**Last Updated:** 2025-11-01 | **Plugin Version:** 1.1.0
