# Orkla Water Level - Supabase Integration Guide

## Overview

The Orkla Water Level WordPress plugin has been successfully rebuilt to use Supabase as the backend database. This provides better performance, scalability, and reliability for storing and querying water level data.

## What's Changed

### Backend (PHP)
- **New Supabase Client**: `includes/class-supabase-client.php` handles all database communication
- **Simplified AJAX Handler**: Clean, efficient data fetching from Supabase
- **Removed WordPress DB dependency**: No more complex WordPress database queries

### Frontend (JavaScript)
- **Rebuilt frontend.js**: Clean, well-documented Chart.js implementation
- **Better error handling**: User-friendly error messages for all scenarios
- **Improved chart rendering**: Reliable graph display with proper data formatting

### Database
- **Supabase PostgreSQL**: Professional time-series database with proper indexing
- **169 sample records**: Pre-loaded test data for the last 7 days
- **Optimized queries**: Fast data retrieval with time-range functions

## System Architecture

```
WordPress Page
    ↓
[orkla_water_level] shortcode
    ↓
Frontend JavaScript (frontend.js)
    ↓
AJAX Request (wp-admin/admin-ajax.php)
    ↓
PHP Handler (ajax_get_water_data)
    ↓
Supabase Client (class-supabase-client.php)
    ↓
Supabase Database (PostgreSQL)
    ↓
Returns formatted JSON data
    ↓
Chart.js renders graph
```

## Installation & Setup

### 1. Upload Plugin Files
Upload the entire `orkla-water-level` directory to your WordPress `wp-content/plugins/` folder.

### 2. Activate Plugin
Go to WordPress Admin → Plugins → Activate "Orkla Water Level Monitor"

### 3. Configure Environment Variables
The plugin reads Supabase credentials from environment variables. These should already be configured in your `.env` file:

```env
VITE_SUPABASE_URL=https://hcqouplxmkjrqzvfzlic.supabase.co
VITE_SUPABASE_SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Alternatively, you can add these to your `wp-config.php`:

```php
define('SUPABASE_URL', 'https://hcqouplxmkjrqzvfzlic.supabase.co');
define('SUPABASE_ANON_KEY', 'your_anon_key_here');
```

### 4. Verify Installation
Upload `test-supabase-integration.php` to your WordPress root directory and access it via browser:
```
https://yoursite.com/test-supabase-integration.php
```

This test page will verify:
- ✓ Supabase credentials are configured
- ✓ Supabase client can connect
- ✓ Data can be queried
- ✓ All plugin files are present
- ✓ Shortcodes are registered

**Important**: Delete this test file after verification!

## Usage

### Basic Shortcode
Add this to any WordPress page or post:
```
[orkla_water_level]
```

### Shortcode with Options
```
[orkla_water_level period="week" height="500px" show_controls="true"]
```

**Available Parameters:**
- `period`: Initial time period to display (default: "today")
  - Options: `today`, `week`, `month`, `year`
- `height`: Chart container height (default: "400px")
- `show_controls`: Show period selector and refresh button (default: "true")

### Example WordPress Page
```html
<h2>Orkla Water Level Monitoring</h2>
<p>Live water flow data from stations along the Orkla river.</p>

[orkla_water_level period="today" height="500px"]

<h3>Weekly Trends</h3>
[orkla_water_level period="week" height="400px" show_controls="false"]
```

## Data Structure

### Supabase Table: `water_level_data`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigserial | Primary key |
| `measured_at` | timestamptz | Measurement timestamp |
| `date_recorded` | date | Date portion |
| `time_recorded` | time | Time portion |
| `vannforing_storsteinsholen` | decimal(10,2) | Storsteinshølen flow |
| `vannforing_brattset` | decimal(10,2) | Brattset upstream flow |
| `vannforing_syrstad` | decimal(10,2) | Syrstad flow |
| `produksjon_brattset` | decimal(10,2) | Brattset production |
| `produksjon_grana` | decimal(10,2) | Grana production |
| `produksjon_svorkmo` | decimal(10,2) | Svorkmo production |
| `rennebu_oppstroms` | decimal(10,2) | Rennebu upstream level |
| `nedstroms_svorkmo` | decimal(10,2) | Svorkmo downstream level |
| `water_temperature` | decimal(10,2) | Water temperature |

## Importing Data

### Method 1: Using Supabase Client in PHP
```php
$supabase_client = new Orkla_Supabase_Client();

$data = array(
    'measured_at' => '2025-10-31 12:00:00+00',
    'date_recorded' => '2025-10-31',
    'time_recorded' => '12:00:00',
    'vannforing_brattset' => 55.5,
    'vannforing_syrstad' => 48.2,
    // ... other fields
);

$result = $supabase_client->insert_water_data($data);
```

### Method 2: Batch Import from CSV
```php
$csv_data = array(/* parsed CSV rows */);
$formatted_data = array();

foreach ($csv_data as $row) {
    $formatted_data[] = array(
        'measured_at' => $row['timestamp'],
        'date_recorded' => date('Y-m-d', strtotime($row['timestamp'])),
        'time_recorded' => date('H:i:s', strtotime($row['timestamp'])),
        'vannforing_brattset' => floatval($row['brattset']),
        // ... map other columns
    );
}

$result = $supabase_client->batch_insert_water_data($formatted_data);
```

### Method 3: Direct SQL Insert
Use the Supabase dashboard SQL editor to insert data directly.

## API Endpoints

### Get Water Data (AJAX)
**Endpoint**: `wp-admin/admin-ajax.php`
**Method**: POST
**Action**: `get_water_data`

**Parameters**:
```javascript
{
    action: 'get_water_data',
    period: 'today',  // or 'week', 'month', 'year', 'year:2024'
    nonce: 'wp_nonce_value'
}
```

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "timestamp": "2025-10-31T12:00:00+00:00",
            "vannforing_brattset": "55.50",
            "vannforing_syrstad": "48.20",
            "vannforing_storsteinsholen": "45.00",
            "produksjon_brattset": "35.00",
            "produksjon_grana": "30.00",
            "produksjon_svorkmo": "40.00",
            "rennebu_oppstroms": "5.50",
            "nedstroms_svorkmo": "55.00",
            "water_temperature": "8.60"
        },
        // ... more records
    ]
}
```

## Troubleshooting

### Graphs Not Displaying

**Check Browser Console (F12)**:
1. Look for any JavaScript errors
2. Check if Chart.js loaded successfully
3. Verify AJAX requests are completing

**Common Issues**:
- **"orkla_ajax is not defined"**: Scripts not enqueued properly. Clear cache and refresh.
- **"Chart.js is NOT loaded"**: Check if Chart.js files exist in `assets/js/vendor/`
- **"No data available"**: Check Supabase connection and verify data exists in database

### Supabase Connection Issues

1. **Verify credentials in .env file**
   ```bash
   cat .env
   ```

2. **Test connection manually**
   ```bash
   curl https://hcqouplxmkjrqzvfzlic.supabase.co/rest/v1/water_level_data?limit=1 \
     -H "apikey: YOUR_ANON_KEY"
   ```

3. **Check WordPress error logs**
   ```bash
   tail -f /path/to/wordpress/wp-content/debug.log
   ```

### AJAX Request Failing

1. **Check WordPress AJAX URL**
   ```javascript
   console.log(orkla_ajax.ajax_url);
   ```

2. **Verify nonce is valid**
   - Nonces expire after 24 hours
   - Clear WordPress caches

3. **Check server error logs**
   - Look for PHP errors in AJAX handler

## Performance Optimization

### Database Indexes
The following indexes are created automatically:
- `idx_water_level_measured_at` - Fast time-range queries
- `idx_water_level_date` - Efficient date-based filtering

### Caching Strategy
- Browser caches Chart.js libraries (version-based)
- Supabase uses connection pooling
- Consider adding WordPress object caching for frequent queries

### Query Limits
- Default limit: 10,000 records per query
- Adjust in `class-supabase-client.php` if needed:
  ```php
  'limit' => 10000  // Change this value
  ```

## Security

### Row Level Security (RLS)
Supabase table has RLS enabled with the following policies:
- **Public read access**: Anyone can view water level data (anon role)
- **Authenticated insert**: Authenticated users can add data
- **Service role only**: Only service role can update/delete

### AJAX Security
- Nonce verification on all AJAX requests
- Input sanitization using WordPress functions
- Proper escaping of output data

## File Structure

```
orkla-water-level/
├── orkla-water-level.php          # Main plugin file
├── includes/
│   ├── class-supabase-client.php  # NEW: Supabase database client
│   ├── class-orkla-hydapi-client.php
│   ├── class-orkla-health-monitor.php
│   └── class-orkla-import-optimizer.php
├── assets/
│   ├── js/
│   │   ├── frontend.js            # REBUILT: Clean Chart.js implementation
│   │   ├── frontend-backup.js     # Backup of old version
│   │   ├── admin.js
│   │   └── vendor/
│   │       ├── chart.min.js       # Chart.js 3.9.1
│   │       └── chartjs-adapter-date-fns.bundle.min.js
│   └── css/
│       ├── frontend.css
│       └── admin.css
├── templates/
│   ├── water-level-widget.php     # Shortcode template
│   ├── water-meter.php
│   └── ...
├── test-supabase-integration.php  # NEW: Test file for verification
└── SUPABASE-INTEGRATION-GUIDE.md  # This file
```

## Maintenance

### Regular Tasks
1. **Monitor database size**: Check Supabase dashboard for storage usage
2. **Clean old data**: Remove records older than needed retention period
3. **Update Chart.js**: Keep visualization library up to date
4. **Check error logs**: Review WordPress and Supabase logs regularly

### Backup Strategy
1. **Supabase automatic backups**: Enabled by default (7-day retention)
2. **Manual exports**: Use Supabase dashboard to export data as CSV/SQL
3. **WordPress plugin files**: Include in regular site backups

## Support & Documentation

### Resources
- **Supabase Dashboard**: https://hcqouplxmkjrqzvfzlic.supabase.co
- **Chart.js Documentation**: https://www.chartjs.org/docs/latest/
- **WordPress Plugin Development**: https://developer.wordpress.org/plugins/

### Getting Help
1. Check browser console for JavaScript errors
2. Review WordPress debug logs for PHP errors
3. Test Supabase connection using test file
4. Verify all plugin files are present and readable

## Changelog

### Version 1.1.0 - Supabase Integration (2025-10-31)
- ✓ Migrated from WordPress database to Supabase PostgreSQL
- ✓ Created new Supabase client class for database operations
- ✓ Rebuilt AJAX endpoint for clean data fetching
- ✓ Rewrote frontend.js with improved error handling
- ✓ Added comprehensive test file for verification
- ✓ Populated sample data (169 records over 7 days)
- ✓ Improved Chart.js rendering reliability
- ✓ Added detailed documentation and troubleshooting guide

### Previous Versions
See IMPLEMENTATION-SUMMARY.md for complete version history.

---

**Last Updated**: October 31, 2025
**Author**: AI Assistant
**WordPress Version**: 6.0+
**PHP Version**: 7.4+
**Chart.js Version**: 3.9.1
