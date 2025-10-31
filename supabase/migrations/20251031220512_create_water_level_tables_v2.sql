/*
  # Orkla Water Level Monitoring System

  ## Overview
  Creates the database structure for storing and querying water level, flow rate, 
  and temperature data from multiple monitoring stations along the Orkla river.

  ## Tables Created

  ### water_level_data
  Main table storing all water monitoring measurements

  ## Security
  - Row Level Security enabled with public read access
  - Authenticated users can insert data for imports
  - Only service role can update/delete
*/

-- Create the main water level data table
CREATE TABLE IF NOT EXISTS water_level_data (
    id bigserial PRIMARY KEY,
    measured_at timestamptz NOT NULL UNIQUE,
    date_recorded date NOT NULL,
    time_recorded time NOT NULL,
    vannforing_storsteinsholen decimal(10,2),
    vannforing_brattset decimal(10,2),
    vannforing_syrstad decimal(10,2),
    produksjon_brattset decimal(10,2),
    produksjon_grana decimal(10,2),
    produksjon_svorkmo decimal(10,2),
    rennebu_oppstroms decimal(10,2),
    nedstroms_svorkmo decimal(10,2),
    water_temperature decimal(10,2),
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

-- Create indexes for efficient querying
CREATE INDEX IF NOT EXISTS idx_water_level_date ON water_level_data(date_recorded);
CREATE INDEX IF NOT EXISTS idx_water_level_measured_at ON water_level_data(measured_at DESC);
CREATE INDEX IF NOT EXISTS idx_water_level_created ON water_level_data(created_at DESC);

-- Enable Row Level Security
ALTER TABLE water_level_data ENABLE ROW LEVEL SECURITY;

-- Policy: Allow public read access
CREATE POLICY "Allow public read access"
    ON water_level_data
    FOR SELECT
    TO anon
    USING (true);

-- Policy: Allow authenticated users to read
CREATE POLICY "Allow authenticated read access"
    ON water_level_data
    FOR SELECT
    TO authenticated
    USING (true);

-- Policy: Allow authenticated users to insert
CREATE POLICY "Allow authenticated insert"
    ON water_level_data
    FOR INSERT
    TO authenticated
    WITH CHECK (true);

-- Create function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for updated_at
DROP TRIGGER IF EXISTS update_water_level_data_updated_at ON water_level_data;
CREATE TRIGGER update_water_level_data_updated_at
    BEFORE UPDATE ON water_level_data
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Create function for time-range queries
CREATE OR REPLACE FUNCTION get_water_data_by_period(
    period_type text DEFAULT 'today',
    custom_start timestamptz DEFAULT NULL,
    custom_end timestamptz DEFAULT NULL
)
RETURNS TABLE (
    measured_at timestamptz,
    vannforing_storsteinsholen decimal,
    vannforing_brattset decimal,
    vannforing_syrstad decimal,
    produksjon_brattset decimal,
    produksjon_grana decimal,
    produksjon_svorkmo decimal,
    rennebu_oppstroms decimal,
    nedstroms_svorkmo decimal,
    water_temperature decimal
) AS $$
DECLARE
    start_time timestamptz;
    end_time timestamptz;
BEGIN
    end_time := now();
    
    CASE period_type
        WHEN 'today' THEN
            start_time := date_trunc('day', now());
        WHEN 'week' THEN
            start_time := now() - interval '7 days';
        WHEN 'month' THEN
            start_time := now() - interval '30 days';
        WHEN 'year' THEN
            start_time := now() - interval '1 year';
        WHEN 'custom' THEN
            start_time := custom_start;
            end_time := custom_end;
        ELSE
            start_time := date_trunc('day', now());
    END CASE;
    
    RETURN QUERY
    SELECT 
        w.measured_at,
        w.vannforing_storsteinsholen,
        w.vannforing_brattset,
        w.vannforing_syrstad,
        w.produksjon_brattset,
        w.produksjon_grana,
        w.produksjon_svorkmo,
        w.rennebu_oppstroms,
        w.nedstroms_svorkmo,
        w.water_temperature
    FROM water_level_data w
    WHERE w.measured_at >= start_time 
        AND w.measured_at <= end_time
    ORDER BY w.measured_at ASC;
END;
$$ LANGUAGE plpgsql STABLE;
