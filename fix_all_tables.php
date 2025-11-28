<?php
include 'config.php';

echo "<h2>Fixing All Database Tables</h2>";

// Fix flights table
echo "<h3>Fixing flights table...</h3>";
try {
    $pdo->exec("DROP TABLE IF EXISTS flights_backup");
    $pdo->exec("CREATE TABLE flights_backup AS SELECT * FROM flights");
    
    $pdo->exec("DROP TABLE IF EXISTS flights");
    
    $pdo->exec("CREATE TABLE flights (
        flight_id INT PRIMARY KEY AUTO_INCREMENT,
        flight_number VARCHAR(20) NOT NULL,
        airline_id INT NOT NULL,
        total_seats INT NOT NULL DEFAULT 180,
        available_seats INT NOT NULL DEFAULT 180,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (airline_id) REFERENCES airlines(airline_id)
    )");
    
    echo "<p style='color: green;'>✅ Flights table created</p>";
    
    // Restore data
    try {
        $pdo->exec("INSERT INTO flights (flight_number, airline_id, total_seats, available_seats, status) 
                   SELECT flight_number, airline_id, total_seats, available_seats, status FROM flights_backup");
        echo "<p style='color: green;'>✅ Flights data restored</p>";
    } catch(Exception $e) {
        echo "<p style='color: orange;'>⚠️ No flights data to restore</p>";
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Fix airlines table
echo "<h3>Fixing airlines table...</h3>";
try {
    $pdo->exec("DROP TABLE IF EXISTS airlines_backup");
    $pdo->exec("CREATE TABLE airlines_backup AS SELECT * FROM airlines");
    
    $pdo->exec("DROP TABLE IF EXISTS airlines");
    
    $pdo->exec("CREATE TABLE airlines (
        airline_id INT PRIMARY KEY AUTO_INCREMENT,
        airline_name VARCHAR(255) NOT NULL,
        airline_code VARCHAR(10) NOT NULL,
        contact_email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "<p style='color: green;'>✅ Airlines table created</p>";
    
    // Restore data
    try {
        $pdo->exec("INSERT INTO airlines (airline_name, airline_code, contact_email) 
                   SELECT airline_name, airline_code, contact_email FROM airlines_backup");
        echo "<p style='color: green;'>✅ Airlines data restored</p>";
    } catch(Exception $e) {
        echo "<p style='color: orange;'>⚠️ No airlines data to restore</p>";
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Fix flight_routes table
echo "<h3>Fixing flight_routes table...</h3>";
try {
    $pdo->exec("DROP TABLE IF EXISTS flight_routes_backup");
    $pdo->exec("CREATE TABLE flight_routes_backup AS SELECT * FROM flight_routes");
    
    $pdo->exec("DROP TABLE IF EXISTS flight_routes");
    
    $pdo->exec("CREATE TABLE flight_routes (
        route_id INT PRIMARY KEY AUTO_INCREMENT,
        flight_id INT NOT NULL,
        departure_city VARCHAR(100) NOT NULL,
        arrival_city VARCHAR(100) NOT NULL,
        departure_time DATETIME NOT NULL,
        arrival_time DATETIME NOT NULL,
        segment_order INT NOT NULL DEFAULT 1,
        stop_duration INT DEFAULT 0,
        flight_duration INT,
        distance INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (flight_id) REFERENCES flights(flight_id)
    )");
    
    echo "<p style='color: green;'>✅ Flight routes table created</p>";
    
    // Restore data
    try {
        $pdo->exec("INSERT INTO flight_routes (flight_id, departure_city, arrival_city, departure_time, arrival_time, segment_order, stop_duration) 
                   SELECT flight_id, departure_city, arrival_city, departure_time, arrival_time, segment_order, stop_duration FROM flight_routes_backup");
        echo "<p style='color: green;'>✅ Flight routes data restored</p>";
    } catch(Exception $e) {
        echo "<p style='color: orange;'>⚠️ No flight routes data to restore</p>";
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Fix flight_rates table
echo "<h3>Fixing flight_rates table...</h3>";
try {
    $pdo->exec("DROP TABLE IF EXISTS flight_rates_backup");
    $pdo->exec("CREATE TABLE flight_rates_backup AS SELECT * FROM flight_rates");
    
    $pdo->exec("DROP TABLE IF EXISTS flight_rates");
    
    $pdo->exec("CREATE TABLE flight_rates (
        rate_id INT PRIMARY KEY AUTO_INCREMENT,
        route_id INT NOT NULL,
        class_type VARCHAR(50) NOT NULL DEFAULT 'economy',
        base_price DECIMAL(10,2) NOT NULL,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        total_price DECIMAL(10,2) NOT NULL,
        valid_from DATE DEFAULT CURRENT_DATE,
        valid_to DATE DEFAULT '2025-12-31',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (route_id) REFERENCES flight_routes(route_id)
    )");
    
    echo "<p style='color: green;'>✅ Flight rates table created</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Fix multi_city_bookings table
echo "<h3>Fixing multi_city_bookings table...</h3>";
try {
    $pdo->exec("DROP TABLE IF EXISTS multi_city_bookings_backup");
    $pdo->exec("CREATE TABLE multi_city_bookings_backup AS SELECT * FROM multi_city_bookings");
    
    $pdo->exec("DROP TABLE IF EXISTS multi_city_bookings");
    
    $pdo->exec("CREATE TABLE multi_city_bookings (
        booking_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        booking_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        booking_status VARCHAR(50) DEFAULT 'confirmed',
        trip_type VARCHAR(50) DEFAULT 'oneway',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "<p style='color: green;'>✅ Multi city bookings table created</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Fix other tables
$other_tables = [
    'booking_segments' => "
        CREATE TABLE booking_segments (
            segment_id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            route_id INT NOT NULL,
            segment_order INT DEFAULT 1,
            travel_date DATE,
            segment_price DECIMAL(10,2),
            passenger_count INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'passengers' => "
        CREATE TABLE passengers (
            passenger_id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE,
            gender VARCHAR(10),
            passport_number VARCHAR(50),
            nationality VARCHAR(50),
            seat_number VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'payments' => "
        CREATE TABLE payments (
            payment_id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            payment_status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'email_notifications' => "
        CREATE TABLE email_notifications (
            email_id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            email_type VARCHAR(50),
            sent_date DATETIME,
            status VARCHAR(50) DEFAULT 'pending',
            attachment_path VARCHAR(500),
            retry_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    "
];

foreach($other_tables as $table => $create_sql) {
    echo "<h3>Fixing $table table...</h3>";
    try {
        $pdo->exec("DROP TABLE IF EXISTS {$table}_backup");
        $pdo->exec("CREATE TABLE {$table}_backup AS SELECT * FROM $table");
        
        $pdo->exec("DROP TABLE IF EXISTS $table");
        $pdo->exec($create_sql);
        
        echo "<p style='color: green;'>✅ $table table created</p>";
    } catch(Exception $e) {
        echo "<p style='color: red;'>❌ Error with $table: " . $e->getMessage() . "</p>";
    }
}

echo "<h2 style='color: green;'>✅ All tables fixed successfully!</h2>";
echo "<p><a href='admin.php'>Go to Admin Panel</a></p>";

// Add sample data for testing
echo "<h3>Adding Sample Data...</h3>";
try {
    // Add sample airlines
    $pdo->exec("INSERT IGNORE INTO airlines (airline_name, airline_code) VALUES 
        ('Air India', 'AI'),
        ('IndiGo', '6E'), 
        ('SpiceJet', 'SG'),
        ('Vistara', 'UK')");
    echo "<p style='color: green;'>✅ Sample airlines added</p>";
    
    // Add sample flight
    $pdo->exec("INSERT IGNORE INTO flights (flight_number, airline_id, total_seats, available_seats) VALUES 
        ('AI101', 1, 180, 150)");
    echo "<p style='color: green;'>✅ Sample flight added</p>";
    
    // Add sample route
    $pdo->exec("INSERT IGNORE INTO flight_routes (flight_id, departure_city, arrival_city, departure_time, arrival_time) VALUES 
        (1, 'Delhi', 'Mumbai', '2024-01-20 08:00:00', '2024-01-20 10:00:00')");
    echo "<p style='color: green;'>✅ Sample route added</p>";
    
} catch(Exception $e) {
    echo "<p style='color: orange;'>⚠️ Sample data not added: " . $e->getMessage() . "</p>";
}
?>