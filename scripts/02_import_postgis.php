<?php
/**
 * PostGIS Installation Guide for Ubuntu:
 * 1. Install PostgreSQL and PostGIS:
 *    sudo apt-get update
 *    sudo apt-get install postgresql postgresql-contrib
 *    sudo apt-get install postgresql-17-postgis-3 postgresql-17-postgis-3-scripts
 * 
 * 2. Install PHP PostgreSQL extension:
 *    sudo apt-get install php-pgsql
 * 
 * 3. Create database and enable PostGIS:
 *    sudo -u postgres psql
 *    CREATE DATABASE your_db_name;
 *    \c your_db_name
 *    CREATE EXTENSION postgis;
 * 
 * 4. Create database user and grant permissions:
 *    CREATE USER your_user WITH PASSWORD 'your_password';
 *    GRANT ALL PRIVILEGES ON DATABASE your_db_name TO your_user;
 *    GRANT ALL ON SCHEMA public TO your_user;
 *    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO your_user;
 *    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO your_user;
 *    \q
 * 
 * Note: If you're using a different PostgreSQL version, replace '17' in the package name
 * with your version number (e.g., '16', '15', etc.)
 */

require_once 'vendor/autoload.php';

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

// Function to create PostGIS table
function createPostgisTable($pdo) {
    try {
        // Drop existing table if it exists
        $pdo->exec("DROP TABLE IF EXISTS fishfarms;");
        echo "Dropped existing table fishfarms\n";
        
        // Create the table with PostGIS extension
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS fishfarms (
                id SERIAL PRIMARY KEY,
                type VARCHAR(50),
                sid VARCHAR(50),
                dataid VARCHAR(50),
                county VARCHAR(50),
                town VARCHAR(50),
                daun VARCHAR(50),
                parcel VARCHAR(50),
                fishfarm VARCHAR(255),
                area DECIMAL,
                issue VARCHAR(255),
                remark TEXT,
                date_announce DATE,
                date_version DATE,
                center VARCHAR(255),
                geoarea DECIMAL,
                shape TEXT,
                content TEXT,
                geometry geometry(Geometry, 4326)
            );
        ");
        
        // Create spatial index
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_fishfarms_geometry 
            ON fishfarms USING GIST (geometry);
        ");
        
        echo "Created table fishfarms\n";
    } catch (PDOException $e) {
        echo "Error creating table fishfarms: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Function to safely convert to numeric
function safeNumeric($value) {
    if (is_array($value)) {
        $value = json_encode($value);
    }
    
    // Handle scientific notation
    if (preg_match('/^[0-9.]+[eE][+-]?[0-9]+$/', $value)) {
        $value = number_format((float)$value, 10, '.', '');
    }
    
    // Remove any non-numeric characters except decimal point
    $value = preg_replace('/[^0-9.-]/', '', $value);
    
    // Handle negative scientific notation (e.g., "4.8514688387513-5")
    if (preg_match('/^([0-9.]+)-([0-9]+)$/', $value, $matches)) {
        $value = number_format((float)($matches[1] * pow(10, -$matches[2])), 10, '.', '');
    }
    
    return $value === '' ? null : $value;
}

// Function to import WKT to PostGIS
function importWktToPostgis($filePath, $type, $pdo) {
    try {
        // Read the JSON file
        echo "Reading {$filePath}...\n";
        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new Exception("Failed to read file: {$filePath}");
        }
        
        $json = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }
        
        // Validate JSON structure
        if (!isset($json['results'][0]['fishfarmList'])) {
            throw new Exception("Invalid JSON structure. Expected 'results[0].fishfarmList'");
        }
        
        // Prepare the insert statement
        $stmt = $pdo->prepare("
            INSERT INTO fishfarms (
                type, sid, dataid, county, town, daun, parcel, fishfarm, 
                area, issue, remark, date_announce, date_version, 
                center, geoarea, content, geometry
            ) VALUES (
                :type, :sid, :dataid, :county, :town, :daun, :parcel, :fishfarm,
                :area, :issue, :remark, :date_announce, :date_version,
                :center, :geoarea, :content,
                ST_SetSRID(ST_GeomFromText(:wkt), 4326)
            )
        ");
        
        // Process each fishfarm in the list
        $count = 0;
        foreach ($json['results'][0]['fishfarmList'] as $fishfarm) {
            try {
                // Debug output for first record
                if ($count === 0) {
                    echo "First record shape field: " . print_r($fishfarm['shape'], true) . "\n";
                }
                
                // Convert array fields to JSON strings and handle numeric fields
                $params = [
                    ':type' => $type,
                    ':sid' => is_array($fishfarm['sid']) ? json_encode($fishfarm['sid']) : $fishfarm['sid'],
                    ':dataid' => is_array($fishfarm['dataid']) ? json_encode($fishfarm['dataid']) : $fishfarm['dataid'],
                    ':county' => is_array($fishfarm['county']) ? json_encode($fishfarm['county']) : $fishfarm['county'],
                    ':town' => is_array($fishfarm['town']) ? json_encode($fishfarm['town']) : $fishfarm['town'],
                    ':daun' => is_array($fishfarm['daun']) ? json_encode($fishfarm['daun']) : $fishfarm['daun'],
                    ':parcel' => is_array($fishfarm['parcel']) ? json_encode($fishfarm['parcel']) : $fishfarm['parcel'],
                    ':fishfarm' => is_array($fishfarm['fishfarm']) ? json_encode($fishfarm['fishfarm']) : $fishfarm['fishfarm'],
                    ':area' => safeNumeric($fishfarm['area']),
                    ':issue' => is_array($fishfarm['ISSUE']) ? json_encode($fishfarm['ISSUE']) : $fishfarm['ISSUE'],
                    ':remark' => is_array($fishfarm['remark']) ? json_encode($fishfarm['remark']) : $fishfarm['remark'],
                    ':date_announce' => is_array($fishfarm['date_announce']) ? json_encode($fishfarm['date_announce']) : $fishfarm['date_announce'],
                    ':date_version' => is_array($fishfarm['date_version']) ? json_encode($fishfarm['date_version']) : $fishfarm['date_version'],
                    ':center' => is_array($fishfarm['center']) ? json_encode($fishfarm['center']) : $fishfarm['center'],
                    ':geoarea' => safeNumeric($fishfarm['geoArea']),
                    ':content' => is_array($fishfarm['content']) ? json_encode($fishfarm['content']) : $fishfarm['content'],
                    ':wkt' => $fishfarm['shape']
                ];
                
                // Use GeoPHP to validate and clean the WKT
                $geometry = geoPHP::load($fishfarm['shape'], 'wkt');
                if ($geometry) {
                    $params[':wkt'] = $geometry->out('wkt');
                } else {
                    throw new Exception("Invalid WKT format");
                }
                
                $stmt->execute($params);
                $count++;
                
                if ($count % 1000 === 0) {
                    echo "Processed {$count} features...\n";
                }
            } catch (PDOException $e) {
                echo "PostgreSQL error at record {$count}: " . $e->getMessage() . "\n";
                echo "WKT: " . $fishfarm['shape'] . "\n";
                throw $e; // Re-throw the exception to stop processing
            } catch (Exception $e) {
                echo "Error processing record {$count}: " . $e->getMessage() . "\n";
                echo "WKT: " . $fishfarm['shape'] . "\n";
                throw $e; // Re-throw the exception to stop processing
            }
        }
        
        echo "Successfully imported {$count} features from {$type}\n";
        
    } catch (Exception $e) {
        echo "Error processing {$filePath}: " . $e->getMessage() . "\n";
        throw $e;
    }
}

try {
    // Connect to PostgreSQL
    $dsn = "pgsql:host={$dbParams['host']};port={$dbParams['port']};dbname={$dbParams['dbname']}";
    echo "Connecting to database...\n";
    $pdo = new PDO($dsn, $dbParams['user'], $dbParams['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n";
    
    // Create the table
    createPostgisTable($pdo);
    
    // Process each JSON file in the directory
    $jsonDir = 'raw/json';
    echo "Looking for JSON files in {$jsonDir}...\n";
    $files = glob($jsonDir . '/*.json');
    
    if (empty($files)) {
        throw new Exception("No JSON files found in {$jsonDir}");
    }
    
    echo "Found " . count($files) . " JSON files\n";
    
    foreach ($files as $file) {
        $type = pathinfo($file, PATHINFO_FILENAME);
        echo "Processing file: {$file} (type: {$type})\n";
        importWktToPostgis($file, $type, $pdo);
    }
    
    echo "Import completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 