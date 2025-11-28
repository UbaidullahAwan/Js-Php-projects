<?php
include 'config.php';

echo "<h2>Current Flights in Database</h2>";

$flights = $pdo->query("
    SELECT f.*, a.airline_name 
    FROM flights f 
    JOIN airlines a ON f.airline_id = a.airline_id
")->fetchAll(PDO::FETCH_ASSOC);

if(empty($flights)) {
    echo "<p style='color: orange;'>No flights found in database.</p>";
    echo "<p>Add some flights through admin.php to see them here.</p>";
} else {
    echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Flight No.</th><th>Airline</th><th>Total Seats</th><th>Available</th><th>Status</th></tr>";
    foreach($flights as $flight) {
        echo "<tr>";
        echo "<td>{$flight['flight_id']}</td>";
        echo "<td>{$flight['flight_number']}</td>";
        echo "<td>{$flight['airline_name']}</td>";
        echo "<td>{$flight['total_seats']}</td>";
        echo "<td>{$flight['available_seats']}</td>";
        echo "<td>{$flight['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>