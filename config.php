<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'u939066721_Group_ticket'; // Your exact database name
$username = 'u939066721_User'; // Your exact username
$password = 'User@2025!'; // Your password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Database connected successfully!";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>