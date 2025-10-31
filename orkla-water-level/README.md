# Orkla Water Level WordPress Plugin

A comprehensive WordPress plugin that monitors water level data from the Orkla river, providing real-time visualization and historical archive functionality.

## Features

- **Hourly Data Fetching**: Automatically fetches CSV data from https://orklavannstand.online/VannforingOrkla.csv every hour
- **Interactive Charts**: Beautiful, responsive charts similar to https://orklavannstand.online/vannstand/
- **Multiple Time Periods**: View data for today, last week, last month, or last year
- **Archive System**: Complete historical data with search by specific date, month, or year
- **Admin Dashboard**: Full admin interface for monitoring and managing data
- **Shortcodes**: Easy integration into any WordPress page or post
- **Responsive Design**: Works perfectly on all devices

## Installation

1. Upload the `orkla-water-level` folder to your WordPress plugins directory (`/wp-content/plugins/`)
2. Activate the plugin through the WordPress admin panel
3. The plugin will automatically create the necessary database tables and start fetching data

## Database Schema

The plugin creates a table `wp_orkla_water_data` with the following structure:
- `id`: Auto-increment primary key
- `timestamp`: Full date and time of the measurement
- `date_recorded`: Date portion for easier querying
- `time_recorded`: Time portion
- `water_level`: Water level in meters
- `flow_rate`: Water flow rate (if available)
- `temperature`: Water temperature (if available)
- `created_at`: When the record was inserted

## Usage

### Shortcodes

#### Water Level Widget
```php
[orkla_water_level period="today" height="400px" show_controls="true"]
```

Parameters:
- `period`: "today", "week", "month", or "year" (default: "today")
- `height`: Chart height (default: "400px")
- `show_controls`: Show period selection controls (default: "true")

#### Archive Widget
```php
[orkla_water_archive height="500px"]
```

Parameters:
- `height`: Chart height (default: "500px")

### Admin Interface

Access the admin interface through:
- **Water Level Menu**: Main dashboard with current data and statistics
- **Archive Submenu**: Historical data search and visualization

### Cron Jobs

The plugin automatically schedules an hourly cron job to fetch new data. You can manually trigger data fetching from the admin dashboard.

## Data Source

The plugin fetches data from: https://orklavannstand.online/VannforingOrkla.csv

Expected CSV format:
```
timestamp,water_level,flow_rate,temperature
2025-01-01 12:00:00,2.45,150.5,5.2
```

## Technical Details

### File Structure
```
orkla-water-level/
├── orkla-water-level.php    # Main plugin file
├── templates/               # PHP templates
│   ├── admin-page.php      # Admin dashboard
│   ├── archive-page.php    # Archive interface
│   ├── water-level-widget.php
│   └── archive-widget.php
├── assets/
│   ├── css/
│   │   ├── frontend.css    # Frontend styles
│   │   └── admin.css       # Admin styles
│   └── js/
│       ├── frontend.js     # Frontend JavaScript
│       └── admin.js        # Admin JavaScript
└── README.md
```

### Dependencies

- Chart.js 3.9.1 for data visualization
- Chart.js date adapter for time series charts
- jQuery (included with WordPress)

### AJAX Endpoints

- `get_water_data`: Fetch water level data for specified period
- `search_archive`: Search historical data by date/month/year
- `fetch_csv_data_now`: Manually trigger data fetch

## Browser Compatibility

- Modern browsers with ES6 support
- Internet Explorer 11+ (with polyfills)
- Mobile browsers (iOS Safari, Android Chrome)

## Performance

- Database indexes on timestamp and date columns for fast queries
- Efficient Chart.js rendering with data decimation for large datasets
- Responsive design with CSS Grid and Flexbox
- Optimized AJAX calls with error handling

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### Version 1.0.0
- Initial release
- Hourly data fetching from CSV
- Interactive charts with Chart.js
- Daily, monthly, and yearly archive views
- Admin interface
- Frontend shortcodes
- Responsive design