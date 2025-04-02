# Solar Panels in Fish Farms Data Collection

This project collects and analyzes data about solar panels installed in fish farms in Taiwan. The data is sourced from the [Society of Fishery and Environment Advancement (SFeA)](https://www.sfea.org.tw/).

## Project Structure

```
.
├── docs/
│   └── json/           # Generated GeoJSON files
├── raw/
│   └── json/          # Raw JSON data files
├── scripts/
│   ├── 01_import_json.php    # Import raw JSON data
│   ├── 02_import_postgis.php # Import data to PostGIS
│   └── 03_match_moeaea.php   # Match with MOEAEA solar panel data
└── sql/
    └── 01_create_tables.sql  # Database schema
```

## Data Sources

1. **Fish Farm Data**: Collected from SFeA website, containing information about fish farms including:
   - Location (county, town, parcel)
   - Farm details (area, type, etc.)
   - Geographic boundaries

2. **Solar Panel Data**: From Ministry of Economic Affairs Energy Administration (MOEAEA), containing:
   - Installation details
   - Location coordinates
   - Capacity and area information

## Setup

1. Create a PostgreSQL database named 'sfea'
2. Import the database schema:
   ```bash
   psql -d sfea -f sql/01_create_tables.sql
   ```
3. Run the import scripts in order:
   ```bash
   php scripts/01_import_json.php
   php scripts/02_import_postgis.php
   php scripts/03_match_moeaea.php
   ```

## Output

The project generates a GeoJSON file (`docs/json/fishfarms.json`) containing:
- Fish farm polygons
- Associated solar panel installations
- Combined properties from both data sources

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

Finjon Kiang 