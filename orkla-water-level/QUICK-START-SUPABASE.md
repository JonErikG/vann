# Orkla Water Level - Quick Start Guide

## ⚡ 5-Minute Setup

### 1. Activate Plugin
WordPress Admin → Plugins → Activate "Orkla Water Level Monitor"

### 2. Verify Setup
Upload `test-supabase-integration.php` to WordPress root:
```
https://yoursite.com/test-supabase-integration.php
```

Look for green checkmarks ✓. If everything shows ✓, you're ready!

**Delete the test file after verification.**

### 3. Add to Your Page
Create a new page and add:
```
[orkla_water_level]
```

Publish and view! 🎉

## 📊 What You Get

- **Live water flow graphs** from 8 monitoring stations
- **Interactive charts** with zoom, pan, and tooltips
- **Time period selector** (today, week, month, year)
- **Responsive design** works on mobile and desktop
- **Fast loading** with Supabase backend

## 🎨 Customize

### Change Default Period
```
[orkla_water_level period="week"]
```

### Adjust Height
```
[orkla_water_level height="600px"]
```

### Hide Controls
```
[orkla_water_level show_controls="false"]
```

### Combine Options
```
[orkla_water_level period="month" height="500px" show_controls="true"]
```

## 🔧 Troubleshooting

### No graph showing?
1. Press F12 to open browser console
2. Look for error messages (red text)
3. Common fixes:
   - Clear cache and refresh (Ctrl+Shift+R)
   - Check if test file shows green checkmarks
   - Verify Supabase credentials in `.env`

### "No data available"?
- Import water level data into Supabase
- Or wait for automatic hourly data fetch
- Sample data is already loaded for testing

### AJAX errors?
- Check WordPress error logs
- Verify nonce hasn't expired
- Test AJAX endpoint manually

## 📁 Key Files

```
orkla-water-level/
├── orkla-water-level.php              # Main plugin
├── includes/class-supabase-client.php # Database connection
├── assets/js/frontend.js              # Chart rendering
├── assets/js/vendor/chart.min.js     # Chart library
└── test-supabase-integration.php      # Test file
```

## 🗄️ Database

- **Location**: Supabase PostgreSQL
- **Table**: `water_level_data`
- **Test Data**: 169 records (last 7 days)
- **URL**: https://hcqouplxmkjrqzvfzlic.supabase.co

## 📚 More Information

- **Full Documentation**: See `SUPABASE-INTEGRATION-GUIDE.md`
- **Implementation Details**: See `IMPLEMENTATION-SUMMARY.md`
- **Changelog**: Check main documentation files

## ✅ System Status

After setup, you should have:
- ✓ Plugin activated in WordPress
- ✓ Supabase credentials configured
- ✓ 169 test records in database
- ✓ All JavaScript and CSS files loaded
- ✓ Shortcode working on pages
- ✓ Graphs displaying correctly

## 🚀 Next Steps

1. **Create your monitoring page**
   - Add shortcode to page
   - Customize appearance
   - Publish and share

2. **Import your data**
   - Use CSV import function
   - Set up automatic data fetching
   - Configure data retention

3. **Customize styling**
   - Edit `assets/css/frontend.css`
   - Adjust colors and fonts
   - Match your site design

## 💡 Pro Tips

- Use `period="today"` for real-time monitoring
- Use `period="week"` for trend analysis
- Add multiple shortcodes on same page with different periods
- Combine with text and explanations for better context

---

**Need Help?**
- Check browser console (F12) for errors
- Review `SUPABASE-INTEGRATION-GUIDE.md`
- Test connection with test file
- Check WordPress error logs

**Everything Working?**
Great! Delete `test-supabase-integration.php` and enjoy your water monitoring system! 🌊
