<?php
include 'config.php';

echo "<h2>Flight Routes Table Structure</h2>";

try {
    // Check flight_routes table structure
    $stmt = $pdo->query("DESCRIBE flight_routes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Flight Routes Table Columns:</h3>";
    echo "<table border='1' cellpadding='5'>";
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
    
    // Show sample data
    echo "<h3>Sample Flight Routes Data:</h3>";
    $routes = $pdo->query("SELECT * FROM flight_routes LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . json_encode($routes, JSON_PRETTY_PRINT) . "</pre>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>