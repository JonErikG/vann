# Orkla Water Level Plugin - WordPress Database Consolidation Complete

## Summary

Successfully consolidated the WordPress plugin shortcodes into a unified system. The plugin now uses **ONLY the WordPress database** (no external databases). Backend and frontend communicate correctly with proper field name transformation.

## Changes Made

### 1. Unified Shortcode System

**New Primary Shortcode:**
```
[orkla_water_display type="both" period="today" height="400px"]
```

**Attributes:**
- `type` - Display mode: `graph`, `meter`, or `both` (default: `both`)
- `period` - Time period: `today`, `week`, `month`, `year`, or `year:YYYY` (default: `today`)
- `height` - Graph height (default: `400px`)
- `show_controls` - Show period controls (default: `true`)
- `stations` - Comma-separated station list using WordPress DB column names: `water_level_1`, `water_level_2`, `water_level_3`, `flow_rate_1`, `flow_rate_2`, `flow_rate_3` (default: all)
- `show_temperature` - Show temperature display (default: `true`)
- `reference_max` - Manual reference maximum for percentage calculations

**Legacy Shortcodes (Backwards Compatible):**
- `[orkla_water_level]` - Shows graph only
- `[orkla_water_meter]` - Shows meters only

### 2. Template Consolidation

- **Created:** `templates/water-display.php` - Unified template supporting both graph and meter displays
- **Removed:** `templates/water-level-widget.php` - Old graph-only template
- **Removed:** `templates/water-meter.php` - Old meter-only template

### 3. Field Name Transformation

**WordPress Database Columns → Frontend Display Names:**

The AJAX handler (`ajax_get_water_data`) transforms data from WordPress database to frontend-friendly Norwegian names:

```sql
SELECT timestamp,
    water_level_1 as vannforing_brattset,
    water_level_2 as vannforing_syrstad,
    water_level_3 as vannforing_storsteinsholen,
    flow_rate_1 as produksjon_brattset,
    flow_rate_2 as produksjon_grana,
    flow_rate_3 as produksjon_svorkmo,
    temperature_1 as vanntemperatur_syrstad,
    COALESCE(water_level_1, 0) + COALESCE(flow_rate_1, 0) as rennebu_oppstroms,
    COALESCE(water_level_3, 0) + COALESCE(flow_rate_3, 0) as nedstroms_svorkmo
FROM wp_orkla_water_data
```

This ensures the frontend JavaScript receives data in the expected Norwegian format while the WordPress database uses standard column names.

### 4. Backend Updates

**Modified Functions:**
- `unified_water_display_shortcode()` - New main shortcode handler
- `water_level_shortcode_legacy()` - Backwards compatibility wrapper for `[orkla_water_level]`
- `water_meter_shortcode_legacy()` - Backwards compatibility wrapper for `[orkla_water_meter]`
- `prepare_meter_data()` - New helper function for meter data preparation
- `get_station_definitions()` - Uses WordPress DB column names (`water_level_1`, etc.)

**Removed:**
- All Supabase integration code
- Duplicate shortcode functions

## Data Flow

1. **User requests page with shortcode** → WordPress renders shortcode
2. **Shortcode loads unified template** → Single template supports both displays
3. **JavaScript makes AJAX request** → Calls `ajax_get_water_data()`
4. **Backend queries WordPress database** → Uses `wp_orkla_water_data` table
5. **Data transformed** → Column names converted to Norwegian for frontend
6. **Data returned to frontend** → JavaScript renders chart/meters with Norwegian field names

## Database Schema

**WordPress Table:** `wp_orkla_water_data`

Columns:
- `id` - Primary key
- `timestamp` - Measurement datetime
- `date_recorded` - Date portion
- `time_recorded` - Time portion
- `water_level_1` - Vannføring Oppstrøms Brattset
- `water_level_2` - Vannføring Syrstad
- `water_level_3` - Vannføring Storsteinshølen
- `flow_rate_1` - Produksjonsvannføring Brattset
- `flow_rate_2` - Produksjonsvannføring Grana
- `flow_rate_3` - Produksjon Svorkmo
- `temperature_1` - Vanntemperatur Syrstad
- `created_at` - Record creation timestamp

## Testing

To test the integration:

1. **Add shortcode to a page:**
   ```
   [orkla_water_display type="both"]
   ```

2. **Test graph only:**
   ```
   [orkla_water_level period="week"]
   ```

3. **Test meters only:**
   ```
   [orkla_water_meter stations="water_level_1,water_level_2"]
   ```

4. **Check WordPress error log:**
   - Should see: "Orkla Plugin: Returning X records for period: today"
   - Should see: "Orkla Plugin: First timestamp: ..."

5. **Check browser console:**
   - Should see: "Data loaded: X records"
   - Should see: "Chart rendered successfully" (for graph display)

## Benefits

1. **Single Database:** Uses only WordPress database - no external dependencies
2. **Cleaner Codebase:** Removed duplicate shortcode logic
3. **Consistent Naming:** Field names properly transformed between backend and frontend
4. **Backwards Compatible:** Old shortcodes still work
5. **Flexible Display:** Single shortcode can show graph, meters, or both
6. **Simplified Maintenance:** One template instead of two

## Important Notes

- **Database Source:** WordPress database ONLY (`wp_orkla_water_data` table)
- **No External Databases:** Supabase and other external database code removed
- **Field Name Mapping:** Backend uses `water_level_1` style, frontend receives `vannforing_brattset` style
- **Automatic Transformation:** AJAX handler handles all field name conversion

## File Changes

**Modified:**
- `orkla-water-level/orkla-water-level.php` - Main plugin file with consolidated shortcodes

**Created:**
- `orkla-water-level/templates/water-display.php` - Unified template

**Removed:**
- `orkla-water-level/templates/water-level-widget.php`
- `orkla-water-level/templates/water-meter.php`
- `orkla-water-level/includes/class-orkla-supabase-client.php`
