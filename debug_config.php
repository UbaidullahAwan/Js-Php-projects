<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debugging Connection</h2>";

$host = 'localhost';
// Try both with and without space
$dbname1 = 'Group Ticket';
$dbname2 = 'Group_Ticket';
$username = 'root';
$password = '';

// Test connection with space
echo "<h3>Testing with 'Group Ticket' (with space):</h3>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname1", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connected to 'Group Ticket' successfully!</p>";
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Failed: " . $e->getMessage() . "</p>";
}

// Test connection without space
echo "<h3>Testing with 'Group_Ticket' (without space):</h3>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname2", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connected to 'Group_Ticket' successfully!</p>";
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Failed: " . $e->getMessage() . "</p>";
}

// Test what databases exist
echo "<h3>Available Databases:</h3>";
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($databases as $db) {
        echo "<p>$db</p>";
    }
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Cannot list databases: " . $e->getMessage() . "</p>";
}
?>