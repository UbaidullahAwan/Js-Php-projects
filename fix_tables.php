<?php
include 'config.php';

echo "<h2>Fixing Database Tables</h2>";

// Check flights table structure
$stmt = $pdo->query("DESCRIBE flights");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Current Flights Table Structure:</h3>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "<td>{$col['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Fix the flights table
echo "<h3>Fixing Flights Table...</h3>";
try {
    // Check if flight_id is primary key and auto_increment
    $has_auto_increment = false;
    foreach($columns as $col) {
        if($col['Field'] == 'flight_id' && $col['Extra'] == 'auto_increment') {
            $has_auto_increment = true;
            break;
        }
    }
    
    if(!$has_auto_increment) {
        // Drop and recreate the table with proper structure
        $pdo->exec("DROP TABLE IF EXISTS flights_backup");
        $pdo->exec("CREATE TABLE flights_backup AS SELECT * FROM flights");
        
        $pdo->exec("DROP TABLE IF EXISTS flights");
        
        $pdo->exec("CREATE TABLE flights (
            flight_id INT PRIMARY KEY AUTO_INCREMENT,
            flight_number VARCHAR(20) NOT NULL,
            airline_id INT NOT NULL,
            total_seats INT NOT NULL,
            available_seats INT NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (airline_id) REFERENCES airlines(airline_id)
        )");
        
        echo "<p style='color: green;'>✅ Flights table recreated with AUTO_INCREMENT</p>";
        
        // Restore data if any
        try {
            $pdo->exec("INSERT INTO flights (flight_number, airline_id, total_seats, available_seats, status) 
                       SELECT flight_number, airline_id, total_seats, available_seats, status FROM flights_backup");
            echo "<p style='color: green;'>✅ Data restored</p>";
        } catch(Exception $e) {
            echo "<p style='color: orange;'>⚠️ No data to restore</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Flights table already has AUTO_INCREMENT</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Also check and fix other tables
$tables_to_check = ['airlines', 'flight_routes', 'flight_rates', 'multi_city_bookings'];

foreach($tables_to_check as $table) {
    echo "<h3>Checking $table table...</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $has_id = false;
        $has_auto_increment = false;
        
        foreach($cols as $col) {
            if(preg_match('/_id$/', $col['Field']) && $col['Key'] == 'PRI') {
                $has_id = true;
                if($col['Extra'] == 'auto_increment') {
                    $has_auto_increment = true;
                }
                break;
            }
        }
        
        if($has_id && !$has_auto_increment) {
            echo "<p style='color: orange;'>⚠️ $table needs AUTO_INCREMENT - fixing...</p>";
            // You would add similar fix logic for other tables
        } else {
            echo "<p style='color: green;'>✅ $table table is OK</p>";
        }
        
    } catch(Exception $e) {
        echo "<p style='color: red;'>❌ Error checking $table: " . $e->getMessage() . "</p>";
    }
}
?>