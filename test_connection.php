<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Basic Connection Test</h2>";

// Test 1: Simple connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=Group Ticket', 'root', '');
    echo "<p style='color: green;'>✅ Basic connection successful</p>";
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Basic connection failed: " . $e->getMessage() . "</p>";
}

// Test 2: Check if we can query users table
try {
    $pdo = new PDO('mysql:host=localhost;dbname=Group Ticket', 'root', '');
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $data = $result->fetch();
    echo "<p style='color: green;'>✅ Users table accessible: {$data['count']} records</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Users table error: " . $e->getMessage() . "</p>";
}

// Test 3: Check flights table
try {
    $pdo = new PDO('mysql:host=localhost;dbname=Group Ticket', 'root', '');
    $result = $pdo->query("SELECT COUNT(*) as count FROM flights");
    $data = $result->fetch();
    echo "<p style='color: green;'>✅ Flights table accessible: {$data['count']} records</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Flights table error: " . $e->getMessage() . "</p>";
}
?>