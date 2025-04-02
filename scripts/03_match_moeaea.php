<?php
/**
 * Script to match solar points with fishfarm polygons and generate GeoJSON
 */

// Database connection parameters
$dbParams = [
    'dbname' => 'sfea',
    'user' => 'sfea',
    'password' => 'FinjonKiang',
    'host' => 'localhost',
    'port' => '5432'
];

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connect to PostgreSQL
    $dsn = "pgsql:host={$dbParams['host']};port={$dbParams['port']};dbname={$dbParams['dbname']}";
    echo "Connecting to database...\n";
    $pdo = new PDO($dsn, $dbParams['user'], $dbParams['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n";
    
    // Read the CSV file
    $csvFile = '/home/kiang/public_html/moeaea.gov.tw/processed/solar_points.csv';
    echo "Reading CSV file: {$csvFile}\n";
    
    if (!file_exists($csvFile)) {
        throw new Exception("CSV file not found: {$csvFile}");
    }    
    // Initialize GeoJSON structure
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => []
    ];
    
    // Process CSV file
    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        throw new Exception("Failed to open CSV file");
    }
    
    $headers = fgetcsv($handle, 4096, ',', '"', '\\');
    
    $count = 0;
    $errorCount = 0;
    while (($row = fgetcsv($handle, 4096, ',', '"', '\\')) !== false) {
        $count++;
        if ($count % 100 === 0) {
            echo "Processing record {$count}...\n";
        }
        
        // Create data array from CSV row
        $data = array_combine($headers, $row);
        
        // Skip if no coordinates
        if (empty($data['Longitude']) || empty($data['Latitude'])) {
            continue;
        }
        
        // Validate coordinates
        if (!is_numeric($data['Longitude']) || !is_numeric($data['Latitude'])) {
            echo "Warning: Invalid coordinates in row {$count}: lon={$data['Longitude']}, lat={$data['Latitude']}\n";
            continue;
        }
        
        // Find containing polygon
        $stmt = $pdo->prepare("
            SELECT 
                id, type, sid, dataid, county, town, daun, parcel, fishfarm, 
                area, issue, remark, date_announce, date_version, 
                center, geoarea, content,
                ST_AsGeoJSON(geometry) as geojson
            FROM fishfarms 
            WHERE ST_Contains(geometry, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))
        ");
        
        $stmt->execute([
            ':lon' => $data['Longitude'],
            ':lat' => $data['Latitude']
        ]);
        
        $polygons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($polygons)) {
            foreach ($polygons as $polygon) {
                // Create feature
                $feature = [
                    'type' => 'Feature',
                    'geometry' => json_decode($polygon['geojson'], true),
                    'properties' => array_merge(
                        // Fishfarm properties
                        [
                            'fishfarm_id' => $polygon['id'],
                            'fishfarm_type' => $polygon['type'],
                            'fishfarm_sid' => $polygon['sid'],
                            'fishfarm_dataid' => $polygon['dataid'],
                            'fishfarm_county' => $polygon['county'],
                            'fishfarm_town' => $polygon['town'],
                            'fishfarm_daun' => $polygon['daun'],
                            'fishfarm_parcel' => $polygon['parcel'],
                            'fishfarm_name' => $polygon['fishfarm'],
                            'fishfarm_area' => $polygon['area'],
                            'fishfarm_issue' => $polygon['issue'],
                            'fishfarm_remark' => $polygon['remark'],
                            'fishfarm_date_announce' => $polygon['date_announce'],
                            'fishfarm_date_version' => $polygon['date_version'],
                            'fishfarm_center' => $polygon['center'],
                            'fishfarm_geoarea' => $polygon['geoarea'],
                            'fishfarm_content' => $polygon['content']
                        ],
                        // Solar point properties
                        [
                            'solar_uuid' => $data['uuid'],
                            'solar_year' => $data['申請年度'],
                            'solar_index' => $data['項次'],
                            'solar_company' => $data['業者名稱'],
                            'solar_name' => $data['電廠名稱'],
                            'solar_date' => $data['施工取得日期'],
                            'solar_area' => $data['土地面積'],
                            'solar_capacity' => $data['裝置容量'],
                            'solar_county' => $data['縣市'],
                            'solar_town' => $data['鄉鎮區'],
                            'solar_section' => $data['地段'],
                            'solar_parcel' => $data['地號'],
                            'solar_lon' => $data['Longitude'],
                            'solar_lat' => $data['Latitude']
                        ]
                    )
                ];
                
                $geojson['features'][] = $feature;
            }
        }
    }
    
    fclose($handle);
    
    // Create output directory if it doesn't exist
    $outputDir = 'docs/json';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // Save GeoJSON file
    $outputFile = $outputDir . '/fishfarms.json';
    file_put_contents($outputFile, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Successfully processed {$count} records\n";
    echo "Found " . count($geojson['features']) . " matching features\n";
    echo "Encountered {$errorCount} rows with incorrect number of columns\n";
    echo "Output saved to: {$outputFile}\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 