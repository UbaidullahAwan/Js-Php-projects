<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

include 'config.php';

// Check if uploads directory exists and is writable
$upload_dir = 'uploads/';
$can_upload = is_dir($upload_dir) && is_writable($upload_dir);

// Handle all actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_flight'])) {
        addFlight($pdo);
    } elseif(isset($_POST['update_flight'])) {
        updateFlight($pdo);
    } elseif(isset($_POST['delete_flight'])) {
        deleteFlight($pdo);
    } elseif(isset($_POST['add_airline'])) {
        addAirline($pdo);
    } elseif(isset($_POST['update_airline'])) {
        updateAirline($pdo);
    } elseif(isset($_POST['add_route'])) {
        addRoute($pdo);
    } elseif(isset($_POST['update_route'])) {
        updateRoute($pdo);
    } elseif(isset($_POST['add_multi_route'])) {
        addMultiRoute($pdo);
    } elseif(isset($_POST['update_user_status'])) {
        updateUserStatus($pdo);
    } elseif(isset($_POST['update_booking_status'])) {
        updateBookingStatus($pdo);
    }
} elseif(isset($_GET['action'])) {
    if($_GET['action'] == 'update_status') {
        updateFlightStatus($pdo);
    } elseif($_GET['action'] == 'delete_airline') {
        deleteAirline($pdo);
    } elseif($_GET['action'] == 'delete_route') {
        deleteRoute($pdo);
    } elseif($_GET['action'] == 'delete_user') {
        deleteUser($pdo);
    } elseif($_GET['action'] == 'delete_booking') {
        deleteBooking($pdo);
    }
}

function redirectWithTab($default_tab = 'flights') {
    $current_tab = $_POST['current_tab'] ?? $_GET['tab'] ?? $default_tab;
    header("Location: admin.php?tab=" . urlencode($current_tab));
    exit;
}

function addFlight($pdo) {
    try {
        $flight_number = $_POST['flight_number'];
        $airline_id = $_POST['airline_id'];
        $total_seats = $_POST['total_seats'];
        $available_seats = $_POST['available_seats'];
        $status = $_POST['status'];
        $price = $_POST['price'] ?? 0;
        $currency = $_POST['currency'] ?? 'USD';
        $flight_type = $_POST['flight_type'] ?? 'one_way';
        $stops = $_POST['stops'] ?? 0;
        $baggage_allowance = $_POST['baggage_allowance'] ?? '20kg';
        $meal_included = $_POST['meal_included'] ?? 'no';
        
        $sql = "INSERT INTO flights (flight_number, airline_id, total_seats, available_seats, status, price, currency, flight_type, stops, baggage_allowance, meal_included) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$flight_number, $airline_id, $total_seats, $available_seats, $status, $price, $currency, $flight_type, $stops, $baggage_allowance, $meal_included]);
        
        $_SESSION['message'] = "✅ Flight added successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Database error: " . $e->getMessage();
    }
    redirectWithTab('flights');
}

function updateFlight($pdo) {
    try {
        $flight_id = $_POST['flight_id'];
        $flight_number = $_POST['flight_number'];
        $airline_id = $_POST['airline_id'];
        $total_seats = $_POST['total_seats'];
        $available_seats = $_POST['available_seats'];
        $status = $_POST['status'];
        $price = $_POST['price'] ?? 0;
        $currency = $_POST['currency'] ?? 'USD';
        $flight_type = $_POST['flight_type'] ?? 'one_way';
        $stops = $_POST['stops'] ?? 0;
        $baggage_allowance = $_POST['baggage_allowance'] ?? '20kg';
        $meal_included = $_POST['meal_included'] ?? 'no';
        
        $sql = "UPDATE flights SET flight_number=?, airline_id=?, total_seats=?, available_seats=?, status=?, price=?, currency=?, flight_type=?, stops=?, baggage_allowance=?, meal_included=?
                WHERE flight_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$flight_number, $airline_id, $total_seats, $available_seats, $status, $price, $currency, $flight_type, $stops, $baggage_allowance, $meal_included, $flight_id]);
        
        $_SESSION['message'] = "✅ Flight updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating flight: " . $e->getMessage();
    }
    redirectWithTab('flights');
}

function deleteFlight($pdo) {
    try {
        $flight_id = $_POST['flight_id'];
        
        // First delete all related routes to avoid foreign key constraint violation
        $delete_routes_sql = "DELETE FROM flight_routes WHERE flight_id = ?";
        $delete_routes_stmt = $pdo->prepare($delete_routes_sql);
        $delete_routes_stmt->execute([$flight_id]);
        
        // Then delete the flight
        $sql = "DELETE FROM flights WHERE flight_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$flight_id]);
        
        $_SESSION['message'] = "✅ Flight deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting flight: " . $e->getMessage();
    }
    redirectWithTab('flights');
}

function addAirline($pdo) {
    global $can_upload;
    
    try {
        $airline_name = $_POST['airline_name'];
        $airline_code = $_POST['airline_code'];
        
        // Handle airline logo upload
        $airline_logo = '';
        if($can_upload && isset($_FILES['airline_logo']) && $_FILES['airline_logo']['error'] === UPLOAD_ERR_OK) {
            $airline_logo = uploadLogo($_FILES['airline_logo']);
        }
        
        $sql = "INSERT INTO airlines (airline_name, airline_code, logo_path) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$airline_name, $airline_code, $airline_logo]);
        
        $_SESSION['message'] = "✅ Airline added successfully!" . ($airline_logo ? " Logo uploaded." : "");
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error adding airline: " . $e->getMessage();
    }
    redirectWithTab('airlines');
}

function updateAirline($pdo) {
    global $can_upload;
    
    try {
        $airline_id = $_POST['airline_id'];
        $airline_name = $_POST['airline_name'];
        $airline_code = $_POST['airline_code'];
        
        // Handle airline logo upload
        $airline_logo = '';
        if($can_upload && isset($_FILES['airline_logo']) && $_FILES['airline_logo']['error'] === UPLOAD_ERR_OK) {
            $airline_logo = uploadLogo($_FILES['airline_logo']);
        }
        
        if($airline_logo) {
            $sql = "UPDATE airlines SET airline_name=?, airline_code=?, logo_path=? WHERE airline_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$airline_name, $airline_code, $airline_logo, $airline_id]);
        } else {
            $sql = "UPDATE airlines SET airline_name=?, airline_code=? WHERE airline_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$airline_name, $airline_code, $airline_id]);
        }
        
        $_SESSION['message'] = "✅ Airline updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating airline: " . $e->getMessage();
    }
    redirectWithTab('airlines');
}

function addRoute($pdo) {
    try {
        $flight_id = $_POST['flight_id'];
        $departure_city = $_POST['departure_city'];
        $arrival_city = $_POST['arrival_city'];
        $departure_time = $_POST['departure_time'];
        $arrival_time = $_POST['arrival_time'];
        $segment_order = $_POST['segment_order'] ?? 1;
        $stop_duration = $_POST['stop_duration'] ?? 0;
        $flight_number = $_POST['flight_number'] ?? '';
        $baggage_allowance = $_POST['baggage_allowance'] ?? '20kg';
        $meal_included = $_POST['meal_included'] ?? 'no';
        
        $sql = "INSERT INTO flight_routes (flight_id, departure_city, arrival_city, departure_time, arrival_time, segment_order, stop_duration, flight_number, baggage_allowance, meal_included) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$flight_id, $departure_city, $arrival_city, $departure_time, $arrival_time, $segment_order, $stop_duration, $flight_number, $baggage_allowance, $meal_included]);
        
        $_SESSION['message'] = "✅ Flight route added successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error adding route: " . $e->getMessage();
    }
    redirectWithTab('routes');
}

function updateRoute($pdo) {
    try {
        $route_id = $_POST['route_id'];
        $flight_id = $_POST['flight_id'];
        $departure_city = $_POST['departure_city'];
        $arrival_city = $_POST['arrival_city'];
        $departure_time = $_POST['departure_time'];
        $arrival_time = $_POST['arrival_time'];
        $segment_order = $_POST['segment_order'] ?? 1;
        $stop_duration = $_POST['stop_duration'] ?? 0;
        $flight_number = $_POST['flight_number'] ?? '';
        $baggage_allowance = $_POST['baggage_allowance'] ?? '20kg';
        $meal_included = $_POST['meal_included'] ?? 'no';
        
        $sql = "UPDATE flight_routes SET flight_id=?, departure_city=?, arrival_city=?, departure_time=?, arrival_time=?, segment_order=?, stop_duration=?, flight_number=?, baggage_allowance=?, meal_included=? 
                WHERE route_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$flight_id, $departure_city, $arrival_city, $departure_time, $arrival_time, $segment_order, $stop_duration, $flight_number, $baggage_allowance, $meal_included, $route_id]);
        
        $_SESSION['message'] = "✅ Route updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating route: " . $e->getMessage();
    }
    redirectWithTab('routes');
}

function addMultiRoute($pdo) {
    try {
        $flight_id = $_POST['flight_id'];
        $routes = $_POST['routes'];
        
        // Get flight details
        $flight_sql = "SELECT * FROM flights WHERE flight_id = ?";
        $flight_stmt = $pdo->prepare($flight_sql);
        $flight_stmt->execute([$flight_id]);
        $flight = $flight_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete existing routes for this flight
        $delete_sql = "DELETE FROM flight_routes WHERE flight_id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([$flight_id]);
        
        // Add new routes
        foreach($routes as $index => $route) {
            if(!empty($route['departure_city']) && !empty($route['arrival_city'])) {
                $sql = "INSERT INTO flight_routes (flight_id, departure_city, arrival_city, departure_time, arrival_time, segment_order, stop_duration, flight_number, baggage_allowance, meal_included) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $flight_id, 
                    $route['departure_city'], 
                    $route['arrival_city'], 
                    $route['departure_time'], 
                    $route['arrival_time'], 
                    $index + 1, 
                    $route['stop_duration'] ?? 0,
                    $route['flight_number'] ?? '',
                    $route['baggage_allowance'] ?? '20kg',
                    $route['meal_included'] ?? 'no'
                ]);
            }
        }
        
        $_SESSION['message'] = "✅ Multi-city routes added successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error adding routes: " . $e->getMessage();
    }
    redirectWithTab('multi-routes');
}

function deleteAirline($pdo) {
    try {
        $airline_id = $_GET['airline_id'];
        $sql = "DELETE FROM airlines WHERE airline_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$airline_id]);
        $_SESSION['message'] = "✅ Airline deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting airline: " . $e->getMessage();
    }
    redirectWithTab('airlines');
}

function deleteRoute($pdo) {
    try {
        $route_id = $_GET['route_id'];
        $sql = "DELETE FROM flight_routes WHERE route_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$route_id]);
        $_SESSION['message'] = "✅ Route deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting route: " . $e->getMessage();
    }
    redirectWithTab('routes');
}

function updateFlightStatus($pdo) {
    try {
        $flight_id = $_GET['flight_id'];
        $status = $_GET['status'];
        $sql = "UPDATE flights SET status=? WHERE flight_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $flight_id]);
        $_SESSION['message'] = "✅ Flight status updated!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating status: " . $e->getMessage();
    }
    redirectWithTab('flights');
}

function updateUserStatus($pdo) {
    try {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        $sql = "UPDATE users SET is_active=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $user_id]);
        $_SESSION['message'] = "✅ User status updated!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating user status: " . $e->getMessage();
    }
    redirectWithTab('agents');
}

function updateBookingStatus($pdo) {
    try {
        $booking_id = $_POST['booking_id'];
        $status = $_POST['status'];
        $sql = "UPDATE multi_city_bookings SET status=? WHERE booking_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $booking_id]);
        $_SESSION['message'] = "✅ Booking status updated!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error updating booking status: " . $e->getMessage();
    }
    redirectWithTab('bookings');
}

function deleteUser($pdo) {
    try {
        $user_id = $_GET['user_id'];
        $sql = "DELETE FROM users WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $_SESSION['message'] = "✅ User deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting user: " . $e->getMessage();
    }
    redirectWithTab('agents');
}

function deleteBooking($pdo) {
    try {
        $booking_id = $_GET['booking_id'];
        $sql = "DELETE FROM multi_city_bookings WHERE booking_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $_SESSION['message'] = "✅ Booking deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting booking: " . $e->getMessage();
    }
    redirectWithTab('bookings');
}

function uploadLogo($file) {
    $upload_dir = 'uploads/';
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Check for errors
    if($file_error !== UPLOAD_ERR_OK) {
        return '';
    }
    
    // Check file size
    if($file_size > $max_size) {
        return '';
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if(!in_array($file_ext, $allowed_types)) {
        return '';
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;
    
    // Move uploaded file
    if(move_uploaded_file($file_tmp, $destination)) {
        return $destination;
    }
    
    return '';
}

// Get current tab from URL
$current_tab = $_GET['tab'] ?? 'flights';

// Get all data
try {
    $flights = $pdo->query("SELECT f.*, a.airline_name, a.logo_path as airline_logo FROM flights f JOIN airlines a ON f.airline_id = a.airline_id ORDER BY f.flight_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $airlines = $pdo->query("SELECT * FROM airlines ORDER BY airline_name")->fetchAll(PDO::FETCH_ASSOC);
    $routes = $pdo->query("SELECT fr.*, f.flight_number as main_flight_number, f.flight_type, a.airline_name, a.airline_code 
                          FROM flight_routes fr 
                          JOIN flights f ON fr.flight_id = f.flight_id 
                          JOIN airlines a ON f.airline_id = a.airline_id 
                          ORDER BY fr.flight_id, fr.segment_order")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users data - FIXED: Using your actual users table structure
    try {
        $users_table_check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if($users_table_check) {
            // Get users with their basic information from your actual table structure
            $users = $pdo->query("SELECT id, email, first_name, last_name, phone_number, date_of_birth, created_at, is_active 
                                 FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            
            // Add booking statistics for each user
            foreach($users as &$user) {
                try {
                    $bookings_table_check = $pdo->query("SHOW TABLES LIKE 'multi_city_bookings'")->fetch();
                    if($bookings_table_check) {
                        $booking_stats = $pdo->prepare("SELECT 
                            COUNT(*) as total_bookings,
                            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
                            MAX(booking_date) as last_activity
                            FROM multi_city_bookings WHERE user_id = ?");
                        $booking_stats->execute([$user['id']]);
                        $stats = $booking_stats->fetch(PDO::FETCH_ASSOC);
                        
                        $user['total_bookings'] = $stats['total_bookings'] ?? 0;
                        $user['confirmed_bookings'] = $stats['confirmed_bookings'] ?? 0;
                        $user['last_activity'] = $stats['last_activity'] ?? null;
                    } else {
                        $user['total_bookings'] = 0;
                        $user['confirmed_bookings'] = 0;
                        $user['last_activity'] = null;
                    }
                } catch(PDOException $e) {
                    $user['total_bookings'] = 0;
                    $user['confirmed_bookings'] = 0;
                    $user['last_activity'] = null;
                }
            }
            unset($user); // break the reference
        } else {
            $users = [];
        }
    } catch(PDOException $e) {
        $users = [];
    }
    
    // Get bookings data with error handling
    try {
        $bookings_table_check = $pdo->query("SHOW TABLES LIKE 'multi_city_bookings'")->fetch();
        if($bookings_table_check) {
            $bookings = $pdo->query("SELECT b.*, u.email, u.first_name, u.last_name 
                                    FROM multi_city_bookings b 
                                    LEFT JOIN users u ON b.user_id = u.id 
                                    ORDER BY b.booking_date DESC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $bookings = [];
        }
    } catch(PDOException $e) {
        $bookings = [];
    }
    
    // Get statistics with error handling
    $stats = [
        'flights' => $pdo->query("SELECT COUNT(*) FROM flights")->fetchColumn(),
        'airlines' => $pdo->query("SELECT COUNT(*) FROM airlines")->fetchColumn(),
        'routes' => $pdo->query("SELECT COUNT(*) FROM flight_routes")->fetchColumn(),
        'users' => 0,
        'active_users' => 0,
        'bookings' => 0
    ];
    
    // Try to get bookings count
    try {
        $stats['bookings'] = $pdo->query("SELECT COUNT(*) FROM multi_city_bookings")->fetchColumn();
    } catch(PDOException $e) {
        $stats['bookings'] = 0;
    }
    
    // Get user counts from users table
    if(!empty($users)) {
        $stats['users'] = count($users);
        $stats['active_users'] = count(array_filter($users, function($user) {
            return $user['is_active'] == 1;
        }));
    }
    
} catch(PDOException $e) {
    die("Error loading data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            line-height: 1.6;
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        
        .header { 
            background: #2c3e50; 
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            text-align: center;
        }
        
        .tabs { 
            display: flex; 
            background: white; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            overflow: hidden;
            flex-wrap: wrap;
        }
        
        .tab { 
            padding: 15px 20px; 
            cursor: pointer; 
            border: none;  
            background: none; 
            flex: 1; 
            color: black;
            min-width: 120px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .tab.active { 
            background: #3498db; 
            color: white; 
        }
        
        .tab:hover {
            background: #2980b9;
            color: white;
        }
        
        .tab-content { 
            display: none; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
        }
        
        .tab-content.active { 
            display: block; 
        }
        
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        
        .form-group { 
            margin-bottom: 15px; 
        }
        
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: #2c3e50; 
        }
        
        input, select, textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            box-sizing: border-box; 
            font-size: 14px;
        }
        
        button { 
            background: #3498db; 
            color: white; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            margin: 5px; 
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        button:hover { 
            background: #2980b9; 
        }
        
        .btn-danger { 
            background: #e74c3c; 
        }
        
        .btn-danger:hover { 
            background: #c0392b; 
        }
        
        .btn-success { 
            background: #27ae60; 
        }
        
        .btn-success:hover { 
            background: #219a52; 
        }
        
        .btn-warning { 
            background: #f39c12; 
        }
        
        .btn-warning:hover { 
            background: #e67e22; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        
        th { 
            background: #34495e; 
            color: white; 
        }
        
        tr:hover { 
            background: #f8f9fa; 
        }
        
        .message { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
        }
        
        .success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 5px; 
            flex-wrap: wrap; 
        }
        
        .grid-2 { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
        }
        
        .grid-3 { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
        }
        
        .grid-4 { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 20px; 
        }
        
        .required::after { 
            content: " *"; 
            color: red; 
        }
        
        .flight-logo { 
            width: 40px; 
            height: 40px; 
            object-fit: contain; 
            border-radius: 4px; 
        }
        
        .airline-logo { 
            width: 50px; 
            height: 30px; 
            object-fit: contain; 
            border-radius: 3px; 
        }
        
        .price-display { 
            font-weight: bold; 
            color: #27ae60; 
        }
        
        .currency-select { 
            width: 100px; 
        }
        
        .price-input { 
            width: calc(100% - 120px); 
        }
        
        .price-container { 
            display: flex; 
            gap: 10px; 
        }
        
        .route-segment { 
            background: #f8f9fa; 
            padding: 20px; 
            margin: 15px 0; 
            border-radius: 8px; 
            border-left: 4px solid #3498db; 
            position: relative;
        }
        
        .route-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .segment-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .remove-route { 
            background: #e74c3c; 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 12px;
        }
        
        .segment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .flight-type-badge { 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: bold; 
            display: inline-block;
        }
        
        .one-way { 
            background: #e3f2fd; 
            color: #1976d2; 
        }
        
        .round-trip { 
            background: #e8f5e8; 
            color: #2e7d32; 
        }
        
        .multi-city { 
            background: #fff3e0; 
            color: #ef6c00; 
        }
        
        .stops-badge { 
            background: #f3e5f5; 
            color: #7b1fa2; 
            padding: 2px 6px; 
            border-radius: 8px; 
            font-size: 10px; 
            margin-left: 5px; 
        }
        
        .baggage-badge { 
            background: #e1f5fe; 
            color: #0277bd; 
            padding: 2px 6px; 
            border-radius: 8px; 
            font-size: 10px; 
            margin-left: 5px; 
        }
        
        .meal-badge { 
            background: #f1f8e9; 
            color: #558b2f; 
            padding: 2px 6px; 
            border-radius: 8px; 
            font-size: 10px; 
            margin-left: 5px; 
        }
        
        .amenities { 
            display: flex; 
            gap: 10px; 
            margin-top: 5px; 
            flex-wrap: wrap;
        }
        
        .route-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e1e8ed;
        }
        
        .round-trip-section {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border: 2px dashed #3498db;
        }
        
        .round-trip-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .round-trip-title i {
            color: #3498db;
        }
        
        .trip-type-indicator {
            display: inline-block;
            padding: 4px 8px;
            background: #3498db;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .booking-badge {
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
        }
        
        .user-name {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .user-email {
            color: #666;
            font-size: 12px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                min-width: 100%;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }
            
            .price-container {
                flex-direction: column;
            }
            
            .currency-select, .price-input {
                width: 100%;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            button {
                width: 100%;
                margin: 2px 0;
            }
            
            .segment-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .tab-content {
                padding: 15px;
            }
            
            th, td {
                padding: 8px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Flight Management Admin Panel</h1>
            <p>Welcome, Administrator</p>
        </div>

        <?php if(isset($_SESSION['message'])): ?>
            <div class="message <?= strpos($_SESSION['message'], '❌') !== false ? 'error' : 'success' ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card"><h3>Flights</h3><p><?= $stats['flights'] ?></p></div>
            <div class="stat-card"><h3>Airlines</h3><p><?= $stats['airlines'] ?></p></div>
            <div class="stat-card"><h3>Routes</h3><p><?= $stats['routes'] ?></p></div>
            <div class="stat-card"><h3>Bookings</h3><p><?= $stats['bookings'] ?></p></div>
            <div class="stat-card"><h3>Users</h3><p><?= $stats['users'] ?></p></div>
            <div class="stat-card"><h3>Active Users</h3><p><?= $stats['active_users'] ?></p></div>
        </div>

        <div class="tabs">
            <button class="tab <?= $current_tab == 'flights' ? 'active' : '' ?>" onclick="showTab('flights')">Flights</button>
            <button class="tab <?= $current_tab == 'airlines' ? 'active' : '' ?>" onclick="showTab('airlines')">Airlines</button>
            <button class="tab <?= $current_tab == 'routes' ? 'active' : '' ?>" onclick="showTab('routes')">Routes</button>
            <button class="tab <?= $current_tab == 'multi-routes' ? 'active' : '' ?>" onclick="showTab('multi-routes')">Multi-City</button>
            <button class="tab <?= $current_tab == 'agents' ? 'active' : '' ?>" onclick="showTab('agents')">Agents</button>
            <button class="tab <?= $current_tab == 'bookings' ? 'active' : '' ?>" onclick="showTab('bookings')">Bookings</button>
        </div>

        <!-- Flights Tab -->
        <div id="flights-tab" class="tab-content <?= $current_tab == 'flights' ? 'active' : '' ?>">
            <h2>Manage Flights</h2>
            
            <form method="POST">
                <input type="hidden" name="current_tab" value="flights">
                <?php if(isset($_GET['edit_flight'])): 
                    $edit_flight = $pdo->query("SELECT * FROM flights WHERE flight_id = " . intval($_GET['edit_flight']))->fetch(PDO::FETCH_ASSOC);
                ?>
                    <input type="hidden" name="flight_id" value="<?= $edit_flight['flight_id'] ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Flight Number</label>
                        <input type="text" name="flight_number" value="<?= $edit_flight['flight_number'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Airline</label>
                        <select name="airline_id" required>
                            <option value="">Select Airline</option>
                            <?php foreach($airlines as $airline): ?>
                                <option value="<?= $airline['airline_id'] ?>" 
                                    <?= (isset($edit_flight) && $edit_flight['airline_id'] == $airline['airline_id']) ? 'selected' : '' ?>>
                                    <?= $airline['airline_name'] ?> (<?= $airline['airline_code'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid-4">
                    <div class="form-group">
                        <label class="required">Total Seats</label>
                        <input type="number" name="total_seats" value="<?= $edit_flight['total_seats'] ?? '180' ?>" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Available Seats</label>
                        <input type="number" name="available_seats" value="<?= $edit_flight['available_seats'] ?? '180' ?>" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Baggage Allowance</label>
                        <select name="baggage_allowance" required>
                            <option value="15kg" <?= (isset($edit_flight) && $edit_flight['baggage_allowance'] == '15kg') ? 'selected' : '' ?>>15kg</option>
                            <option value="20kg" <?= (isset($edit_flight) && $edit_flight['baggage_allowance'] == '20kg') ? 'selected' : (isset($edit_flight) ? '' : 'selected') ?>>20kg</option>
                            <option value="25kg" <?= (isset($edit_flight) && $edit_flight['baggage_allowance'] == '25kg') ? 'selected' : '' ?>>25kg</option>
                            <option value="30kg" <?= (isset($edit_flight) && $edit_flight['baggage_allowance'] == '30kg') ? 'selected' : '' ?>>30kg</option>
                            <option value="35kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '35kg') ? 'selected' : '' ?>>35kg</option>
                            <option value="40kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '40kg') ? 'selected' : '' ?>>40kg</option>
                            <option value="45kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '45kg') ? 'selected' : '' ?>>45kg</option>
                            <option value="50kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '50kg') ? 'selected' : '' ?>>50kg</option>
                            <option value="2 pieces" <?= (isset($edit_flight) && $edit_flight['baggage_allowance'] == '2 pieces') ? 'selected' : '' ?>>2 pieces</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Meal Included</label>
                        <select name="meal_included" required>
                            <option value="no" <?= (isset($edit_flight) && $edit_flight['meal_included'] == 'no') ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= (isset($edit_flight) && $edit_flight['meal_included'] == 'yes') ? 'selected' : (isset($edit_flight) ? '' : 'selected') ?>>Yes</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid-3">
                    <div class="form-group">
                        <label class="required">Price</label>
                        <div class="price-container">
                            <input type="number" name="price" class="price-input" 
                                   value="<?= $edit_flight['price'] ?? '0' ?>" required min="0" step="0.01" 
                                   placeholder="0.00">
                            <select name="currency" class="currency-select">
                                <option value="USD" <?= (isset($edit_flight) && $edit_flight['currency'] == 'USD') ? 'selected' : '' ?>>USD</option>
                                <option value="EUR" <?= (isset($edit_flight) && $edit_flight['currency'] == 'EUR') ? 'selected' : '' ?>>EUR</option>
                                <option value="GBP" <?= (isset($edit_flight) && $edit_flight['currency'] == 'GBP') ? 'selected' : '' ?>>GBP</option>
                                <option value="PKR" <?= (isset($edit_flight) && $edit_flight['currency'] == 'PKR') ? 'selected' : '' ?>>PKR</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Flight Type</label>
                        <select name="flight_type" id="flightType" required onchange="toggleFlightTypeFields()">
                            <option value="one_way" <?= (isset($edit_flight) && $edit_flight['flight_type'] == 'one_way') ? 'selected' : '' ?>>One Way</option>
                            <option value="round_trip" <?= (isset($edit_flight) && $edit_flight['flight_type'] == 'round_trip') ? 'selected' : '' ?>>Round Trip</option>
                            <option value="multi_city" <?= (isset($edit_flight) && $edit_flight['flight_type'] == 'multi_city') ? 'selected' : '' ?>>Multi-City</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="stopsContainer" style="display: none;">
                        <label>Number of Stops</label>
                        <select name="stops">
                            <option value="0" <?= (isset($edit_flight) && $edit_flight['stops'] == 0) ? 'selected' : '' ?>>Non-stop</option>
                            <option value="1" <?= (isset($edit_flight) && $edit_flight['stops'] == 1) ? 'selected' : '' ?>>1 Stop</option>
                            <option value="2" <?= (isset($edit_flight) && $edit_flight['stops'] == 2) ? 'selected' : '' ?>>2 Stops</option>
                            <option value="3" <?= (isset($edit_flight) && $edit_flight['stops'] == 3) ? 'selected' : '' ?>>3 Stops</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="required">Status</label>
                    <select name="status" required>
                        <option value="active" <?= (isset($edit_flight) && $edit_flight['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="on_hold" <?= (isset($edit_flight) && $edit_flight['status'] == 'on_hold') ? 'selected' : '' ?>>On Hold</option>
                        <option value="cancelled" <?= (isset($edit_flight) && $edit_flight['status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <button type="submit" name="<?= isset($_GET['edit_flight']) ? 'update_flight' : 'add_flight' ?>">
                    <?= isset($_GET['edit_flight']) ? 'Update Flight' : 'Add Flight' ?>
                </button>
                
                <?php if(isset($_GET['edit_flight'])): ?>
                    <a href="admin.php?tab=flights"><button type="button">Cancel</button></a>
                <?php endif; ?>
            </form>

            <h3>All Flights (<?= count($flights) ?>)</h3>
            <?php if(empty($flights)): ?>
                <p>No flights found. Add your first flight above.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>Flight Number</th>
                                <th>Airline</th>
                                <th>Type</th>
                                <th>Seats</th>
                                <th>Price</th>
                                <th>Amenities</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($flights as $flight): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($flight['airline_logo'])): ?>
                                        <img src="<?= $flight['airline_logo'] ?>" alt="Airline Logo" class="flight-logo">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">No Logo</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= $flight['flight_number'] ?></strong></td>
                                <td><?= $flight['airline_name'] ?></td>
                                <td>
                                    <span class="flight-type-badge <?= $flight['flight_type'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $flight['flight_type'])) ?>
                                    </span>
                                    <?php if($flight['stops'] > 0): ?>
                                        <span class="stops-badge"><?= $flight['stops'] ?> stop<?= $flight['stops'] > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $flight['available_seats'] ?>/<?= $flight['total_seats'] ?></td>
                                <td class="price-display">
                                    <?= $flight['currency'] ?> <?= number_format($flight['price'], 2) ?>
                                </td>
                                <td>
                                    <div class="amenities">
                                        <span class="baggage-badge"><?= $flight['baggage_allowance'] ?></span>
                                        <?php if($flight['meal_included'] == 'yes'): ?>
                                            <span class="meal-badge">Meal</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 3px; font-size: 12px; 
                                        background: <?= $flight['status'] == 'active' ? '#d4edda' : 
                                                   ($flight['status'] == 'on_hold' ? '#fff3cd' : '#f8d7da') ?>;
                                        color: <?= $flight['status'] == 'active' ? '#155724' : 
                                                ($flight['status'] == 'on_hold' ? '#856404' : '#721c24') ?>;">
                                        <?= ucfirst($flight['status']) ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="admin.php?edit_flight=<?= $flight['flight_id'] ?>&tab=flights">
                                        <button>Edit</button>
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="current_tab" value="flights">
                                        <input type="hidden" name="flight_id" value="<?= $flight['flight_id'] ?>">
                                        <button type="submit" name="delete_flight" class="btn-danger" 
                                                onclick="return confirm('Delete flight <?= $flight['flight_number'] ?>?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Airlines Tab -->
        <div id="airlines-tab" class="tab-content <?= $current_tab == 'airlines' ? 'active' : '' ?>">
            <h2>Manage Airlines</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="current_tab" value="airlines">
                <?php if(isset($_GET['edit_airline'])): 
                    $edit_airline = $pdo->query("SELECT * FROM airlines WHERE airline_id = " . intval($_GET['edit_airline']))->fetch(PDO::FETCH_ASSOC);
                ?>
                    <input type="hidden" name="airline_id" value="<?= $edit_airline['airline_id'] ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Airline Name</label>
                        <input type="text" name="airline_name" value="<?= $edit_airline['airline_name'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Airline Code</label>
                        <input type="text" name="airline_code" value="<?= $edit_airline['airline_code'] ?? '' ?>" required maxlength="3" placeholder="e.g., AA">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Airline Logo</label>
                    <input type="file" name="airline_logo" accept="image/*">
                    <?php if(isset($edit_airline) && !empty($edit_airline['logo_path'])): ?>
                        <div style="margin-top: 10px;">
                            <img src="<?= $edit_airline['logo_path'] ?>" alt="Airline Logo" style="max-width: 100px; max-height: 50px;">
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="<?= isset($_GET['edit_airline']) ? 'update_airline' : 'add_airline' ?>">
                    <?= isset($_GET['edit_airline']) ? 'Update Airline' : 'Add Airline' ?>
                </button>
                
                <?php if(isset($_GET['edit_airline'])): ?>
                    <a href="admin.php?tab=airlines"><button type="button">Cancel</button></a>
                <?php endif; ?>
            </form>

            <h3>All Airlines (<?= count($airlines) ?>)</h3>
            <?php if(empty($airlines)): ?>
                <p>No airlines found. Add your first airline above.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>Airline Name</th>
                                <th>Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($airlines as $airline): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($airline['logo_path'])): ?>
                                        <img src="<?= $airline['logo_path'] ?>" alt="Airline Logo" class="flight-logo">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">No Logo</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= $airline['airline_name'] ?></strong></td>
                                <td><code><?= $airline['airline_code'] ?></code></td>
                                <td class="action-buttons">
                                    <a href="admin.php?edit_airline=<?= $airline['airline_id'] ?>&tab=airlines">
                                        <button>Edit</button>
                                    </a>
                                    <a href="admin.php?action=delete_airline&airline_id=<?= $airline['airline_id'] ?>&tab=airlines" 
                                       onclick="return confirm('Delete airline <?= $airline['airline_name'] ?>?')">
                                        <button class="btn-danger">Delete</button>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Routes Tab -->
        <div id="routes-tab" class="tab-content <?= $current_tab == 'routes' ? 'active' : '' ?>">
            <h2>Manage Flight Routes</h2>
            
            <form method="POST">
                <input type="hidden" name="current_tab" value="routes">
                <?php if(isset($_GET['edit_route'])): 
                    $edit_route = $pdo->query("SELECT * FROM flight_routes WHERE route_id = " . intval($_GET['edit_route']))->fetch(PDO::FETCH_ASSOC);
                ?>
                    <input type="hidden" name="route_id" value="<?= $edit_route['route_id'] ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Flight</label>
                        <select name="flight_id" required>
                            <option value="">Select Flight</option>
                            <?php foreach($flights as $flight): ?>
                                <option value="<?= $flight['flight_id'] ?>" 
                                    <?= (isset($edit_route) && $edit_route['flight_id'] == $flight['flight_id']) ? 'selected' : '' ?>>
                                    <?= $flight['flight_number'] ?> - <?= $flight['airline_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Segment Order</label>
                        <input type="number" name="segment_order" value="<?= $edit_route['segment_order'] ?? '1' ?>" min="1">
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Departure City</label>
                        <input type="text" name="departure_city" value="<?= $edit_route['departure_city'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Arrival City</label>
                        <input type="text" name="arrival_city" value="<?= $edit_route['arrival_city'] ?? '' ?>" required>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Departure Time</label>
                        <input type="datetime-local" name="departure_time" value="<?= isset($edit_route['departure_time']) ? date('Y-m-d\TH:i', strtotime($edit_route['departure_time'])) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Arrival Time</label>
                        <input type="datetime-local" name="arrival_time" value="<?= isset($edit_route['arrival_time']) ? date('Y-m-d\TH:i', strtotime($edit_route['arrival_time'])) : '' ?>" required>
                    </div>
                </div>
                
                <div class="segment-details">
                    <div class="form-group">
                        <label>Flight Number (Segment)</label>
                        <input type="text" name="flight_number" value="<?= $edit_route['flight_number'] ?? '' ?>" placeholder="Optional - uses main flight number if empty">
                    </div>
                    
                    <div class="form-group">
                        <label>Baggage Allowance</label>
                        <select name="baggage_allowance">
                            <option value="15kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '15kg') ? 'selected' : '' ?>>15kg</option>
                            <option value="20kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '20kg') ? 'selected' : 'selected' ?>>20kg</option>
                            <option value="25kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '25kg') ? 'selected' : '' ?>>25kg</option>
                            <option value="30kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '30kg') ? 'selected' : '' ?>>30kg</option>
                            <option value="35kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '35kg') ? 'selected' : '' ?>>35kg</option>
                            <option value="40kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '40kg') ? 'selected' : '' ?>>40kg</option>
                            <option value="45kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '45kg') ? 'selected' : '' ?>>45kg</option>
                            <option value="50kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '50kg') ? 'selected' : '' ?>>50kg</option>


                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Meal Included</label>
                        <select name="meal_included">
                            <option value="no" <?= (isset($edit_route) && $edit_route['meal_included'] == 'no') ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= (isset($edit_route) && $edit_route['meal_included'] == 'yes') ? 'selected' : 'selected' ?>>Yes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Stop Duration (minutes)</label>
                        <input type="number" name="stop_duration" value="<?= $edit_route['stop_duration'] ?? '0' ?>" min="0">
                    </div>
                </div>
                
                <button type="submit" name="<?= isset($_GET['edit_route']) ? 'update_route' : 'add_route' ?>">
                    <?= isset($_GET['edit_route']) ? 'Update Route' : 'Add Route' ?>
                </button>
                
                <?php if(isset($_GET['edit_route'])): ?>
                    <a href="admin.php?tab=routes"><button type="button">Cancel</button></a>
                <?php endif; ?>
            </form>

            <h3>All Routes (<?= count($routes) ?>)</h3>
            <?php if(empty($routes)): ?>
                <p>No routes found. Add your first route above.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Flight</th>
                                <th>Segment</th>
                                <th>Route</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Flight No.</th>
                                <th>Amenities</th>
                                <th>Stop Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($routes as $route): ?>
                            <tr>
                                <td>
                                    <strong><?= $route['main_flight_number'] ?></strong><br>
                                    <small><?= $route['airline_name'] ?></small>
                                </td>
                                <td><?= $route['segment_order'] ?></td>
                                <td>
                                    <strong><?= $route['departure_city'] ?> → <?= $route['arrival_city'] ?></strong>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($route['departure_time'])) ?></td>
                                <td><?= date('M j, Y H:i', strtotime($route['arrival_time'])) ?></td>
                                <td><?= !empty($route['flight_number']) ? $route['flight_number'] : $route['main_flight_number'] ?></td>
                                <td>
                                    <span class="baggage-badge"><?= $route['baggage_allowance'] ?? '20kg' ?></span>
                                    <?php if(($route['meal_included'] ?? 'no') == 'yes'): ?>
                                        <span class="meal-badge">Meal</span>
                                    <?php endif; ?>
                                    <?php if($route['stop_duration'] > 0): ?>
                                        <span class="stops-badge"><?= $route['stop_duration'] ?>min stop</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $route['stop_duration'] ?> min</td>
                                <td class="action-buttons">
                                    <a href="admin.php?edit_route=<?= $route['route_id'] ?>&tab=routes">
                                        <button>Edit</button>
                                    </a>
                                    <a href="admin.php?action=delete_route&route_id=<?= $route['route_id'] ?>&tab=routes" 
                                       onclick="return confirm('Delete this route segment?')">
                                        <button class="btn-danger">Delete</button>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Multi-City Routes Tab - UPDATED: Removed pricing section -->
        <div id="multi-routes-tab" class="tab-content <?= $current_tab == 'multi-routes' ? 'active' : '' ?>">
            <h2>Manage Multi-City & Round Trip Routes</h2>
            <form method="POST" id="multiRouteForm">
                <input type="hidden" name="current_tab" value="multi-routes">
                
                <div class="form-group">
                    <label class="required">Flight</label>
                    <select name="flight_id" id="multiCityFlight" required onchange="updateFlightType()">
                        <option value="">Select Flight</option>
                        <?php foreach($flights as $flight): ?>
                            <option value="<?= $flight['flight_id'] ?>" data-type="<?= $flight['flight_type'] ?>">
                                <?= $flight['flight_number'] ?> - <?= $flight['airline_name'] ?> (<?= ucfirst($flight['flight_type']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="flightTypeIndicator" style="display: none; margin-bottom: 20px;">
                    <div class="round-trip-title">
                        Flight Type: <span id="selectedFlightType"></span>
                        <span class="trip-type-indicator" id="tripTypeBadge"></span>
                    </div>
                </div>

                <div id="routeSegments">
                    <div class="route-segment" data-segment="1">
                        <div class="route-header">
                            <h3 class="segment-title">Segment 1 - Outbound</h3>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="required">Departure City</label>
                                <input type="text" name="routes[0][departure_city]" required placeholder="e.g., New York">
                            </div>
                            <div class="form-group">
                                <label class="required">Arrival City</label>
                                <input type="text" name="routes[0][arrival_city]" required placeholder="e.g., London">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="required">Departure Date & Time</label>
                                <input type="datetime-local" name="routes[0][departure_time]" required 
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                            <div class="form-group">
                                <label class="required">Arrival Date & Time</label>
                                <input type="datetime-local" name="routes[0][arrival_time]" required 
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        
                        <div class="segment-details">
                            <div class="form-group">
                                <label class="required">Flight Number</label>
                                <input type="text" name="routes[0][flight_number]" required placeholder="e.g., AA123">
                            </div>
                            <div class="form-group">
                                <label class="required">Baggage Allowance</label>
                                <select name="routes[0][baggage_allowance]" required>
                                    <option value="15kg">15kg</option>
                                    <option value="20kg" selected>20kg</option>
                                    <option value="25kg">25kg</option>
                                    <option value="30kg">30kg</option>
                                    <option value="35kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '35kg') ? 'selected' : '' ?>>35kg</option>
                            <option value="40kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '40kg') ? 'selected' : '' ?>>40kg</option>
                            <option value="45kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '45kg') ? 'selected' : '' ?>>45kg</option>
                            <option value="50kg" <?= (isset($edit_route) && $edit_route['baggage_allowance'] == '50kg') ? 'selected' : '' ?>>50kg</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required">Meal Included</label>
                                <select name="routes[0][meal_included]" required>
                                    <option value="no">No</option>
                                    <option value="yes" selected>Yes</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Stop Duration (minutes)</label>
                            <input type="number" name="routes[0][stop_duration]" value="0" min="0" placeholder="Optional">
                        </div>
                    </div>
                </div>

                <div class="route-actions">
                    <button type="button" onclick="addRouteSegment()" class="btn-success">
                        Add Another Segment
                    </button>
                    <button type="submit" name="add_multi_route" id="saveMultiRoute">
                        Save All Routes
                    </button>
                </div>
            </form>

            <h3>Multi-City & Round Trip Flights</h3>
            <?php
            $multi_city_flights = array_filter($flights, function($flight) {
                return in_array($flight['flight_type'], ['multi_city', 'round_trip']);
            });
            ?>
            <?php if(empty($multi_city_flights)): ?>
                <p>No multi-city or round trip flights found.</p>
            <?php else: ?>
                <?php foreach($multi_city_flights as $flight): ?>
                    <div style="background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px;">
                        <h4>
                            <?= $flight['flight_number'] ?> - <?= $flight['airline_name'] ?>
                            <span class="flight-type-badge <?= $flight['flight_type'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $flight['flight_type'])) ?>
                            </span>
                        </h4>
                        <?php
                        $flight_routes = array_filter($routes, function($route) use ($flight) {
                            return $route['flight_id'] == $flight['flight_id'];
                        });
                        ?>
                        <?php if(!empty($flight_routes)): ?>
                            <div style="overflow-x: auto; margin-top: 15px;">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Segment</th>
                                            <th>Route</th>
                                            <th>Flight No.</th>
                                            <th>Departure</th>
                                            <th>Arrival</th>
                                            <th>Baggage</th>
                                            <th>Meal</th>
                                            <th>Stop Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($flight_routes as $route): ?>
                                        <tr>
                                            <td><?= $route['segment_order'] ?></td>
                                            <td><strong><?= $route['departure_city'] ?> → <?= $route['arrival_city'] ?></strong></td>
                                            <td><?= !empty($route['flight_number']) ? $route['flight_number'] : $flight['flight_number'] ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($route['departure_time'])) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($route['arrival_time'])) ?></td>
                                            <td><?= !empty($route['baggage_allowance']) ? $route['baggage_allowance'] : $flight['baggage_allowance'] ?></td>
                                            <td><?= !empty($route['meal_included']) ? ucfirst($route['meal_included']) : ucfirst($flight['meal_included']) ?></td>
                                            <td><?= $route['stop_duration'] ?> min</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="color: #666; font-style: italic;">No routes configured for this flight.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Agents Tab - UPDATED: Now shows actual user data -->
        <div id="agents-tab" class="tab-content <?= $current_tab == 'agents' ? 'active' : '' ?>">
            <h2>Manage Agents & Users</h2>
            
            <div class="stats" style="margin-bottom: 30px;">
                <div class="stat-card"><h3>Total Users</h3><p><?= $stats['users'] ?></p></div>
                <div class="stat-card"><h3>Active Users</h3><p><?= $stats['active_users'] ?></p></div>
                <div class="stat-card"><h3>Total Bookings</h3><p><?= $stats['bookings'] ?></p></div>
            </div>

            <h3>All Users (<?= count($users) ?>)</h3>
            <?php if(empty($users)): ?>
                <div class="message error">
                    <p>No users found in the database.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact Info</th>
                                <th>Personal Details</th>
                                <th>Registration</th>
                                <th>Bookings</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="user-name">
                                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                            </div>
                                            <div class="user-email">
                                                ID: <?= $user['id'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?><br>
                                        <strong>Phone:</strong> <?= !empty($user['phone_number']) ? htmlspecialchars($user['phone_number']) : 'N/A' ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong>DOB:</strong> <?= !empty($user['date_of_birth']) ? date('M j, Y', strtotime($user['date_of_birth'])) : 'N/A' ?><br>
                                    </div>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?><br>
                                    <small style="color: #666;"><?= date('g:i A', strtotime($user['created_at'] ?? 'now')) ?></small>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <div style="font-size: 18px; font-weight: bold; color: #3498db;">
                                            <?= $user['total_bookings'] ?? 0 ?>
                                        </div>
                                        <small style="color: #666;">
                                            Confirmed: <?= $user['confirmed_bookings'] ?? 0 ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($user['last_activity'])): ?>
                                        <?= date('M j, Y', strtotime($user['last_activity'])) ?><br>
                                        <small style="color: #666;"><?= date('g:i A', strtotime($user['last_activity'])) ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">No activity</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="current_tab" value="agents">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding: 6px; border-radius: 4px; border: 1px solid #ddd;">
                                            <option value="1" <?= $user['is_active'] == 1 ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= $user['is_active'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="action-buttons">
                                    <a href="user_details.php?user_id=<?= $user['id'] ?>" target="_blank">
                                        <button class="btn-success" style="padding: 8px 12px;">
                                            View Details
                                        </button>
                                    </a>
                                    <a href="admin.php?action=delete_user&user_id=<?= $user['id'] ?>&tab=agents" 
                                       onclick="return confirm('Delete user <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>? This action cannot be undone.')">
                                        <button class="btn-danger" style="padding: 8px 12px;">
                                            Delete
                                        </button>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bookings Tab -->
        <div id="bookings-tab" class="tab-content <?= $current_tab == 'bookings' ? 'active' : '' ?>">
            <h2>Manage Bookings</h2>
            
            <div class="stats" style="margin-bottom: 30px;">
                <div class="stat-card"><h3>Total Bookings</h3><p><?= $stats['bookings'] ?></p></div>
                <div class="stat-card"><h3>Confirmed</h3><p><?= array_reduce($bookings, function($carry, $booking) { return $carry + ($booking['status'] == 'confirmed' ? 1 : 0); }, 0) ?></p></div>
                <div class="stat-card"><h3>Pending</h3><p><?= array_reduce($bookings, function($carry, $booking) { return $carry + ($booking['status'] == 'pending' ? 1 : 0); }, 0) ?></p></div>
                <div class="stat-card"><h3>Cancelled</h3><p><?= array_reduce($bookings, function($carry, $booking) { return $carry + ($booking['status'] == 'cancelled' ? 1 : 0); }, 0) ?></p></div>
            </div>

            <h3>All Bookings (<?= count($bookings) ?>)</h3>
            <?php if(empty($bookings)): ?>
                <p>No bookings found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>User</th>
                                <th>Flight Details</th>
                                <th>Passengers</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Booking Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?= $booking['booking_id'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?><br>
                                    <small><?= htmlspecialchars($booking['email'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <small>Flight: <?= $booking['flight_number'] ?? 'N/A' ?></small><br>
                                    <small>Routes: <?= $booking['routes_count'] ?? 1 ?></small>
                                </td>
                                <td><?= $booking['passenger_count'] ?? 1 ?> passengers</td>
                                <td>
                                    <strong>$<?= number_format($booking['total_amount'] ?? 0, 2) ?></strong>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="current_tab" value="bookings">
                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding: 6px; border-radius: 4px; border: 1px solid #ddd;">
                                            <option value="pending" <?= ($booking['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= ($booking['status'] ?? 'pending') == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="cancelled" <?= ($booking['status'] ?? 'pending') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($booking['booking_date'])) ?></td>
                                <td class="action-buttons">
                                    <a href="booking_details.php?booking_id=<?= $booking['booking_id'] ?>" target="_blank">
                                        <button class="btn-success">View Details</button>
                                    </a>
                                    <a href="admin.php?action=delete_booking&booking_id=<?= $booking['booking_id'] ?>&tab=bookings" 
                                       onclick="return confirm('Delete booking #<?= $booking['booking_id'] ?>? This action cannot be undone.')">
                                        <button class="btn-danger">Delete</button>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let routeCount = 1;
        
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update hidden field in all forms
            document.querySelectorAll('input[name="current_tab"]').forEach(input => {
                input.value = tabName;
            });
            
            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }

        function toggleFlightTypeFields() {
            const flightType = document.getElementById('flightType').value;
            const stopsContainer = document.getElementById('stopsContainer');
            
            if (flightType === 'multi_city' || flightType === 'round_trip') {
                stopsContainer.style.display = 'none';
            } else {
                stopsContainer.style.display = 'block';
            }
        }

        function updateFlightType() {
            const flightSelect = document.getElementById('multiCityFlight');
            const selectedOption = flightSelect.options[flightSelect.selectedIndex];
            const flightType = selectedOption.getAttribute('data-type');
            const indicator = document.getElementById('flightTypeIndicator');
            const flightTypeSpan = document.getElementById('selectedFlightType');
            const tripTypeBadge = document.getElementById('tripTypeBadge');
            
            if (flightType) {
                indicator.style.display = 'block';
                flightTypeSpan.textContent = flightType.replace('_', ' ');
                tripTypeBadge.textContent = flightType.toUpperCase();
                tripTypeBadge.className = 'trip-type-indicator ' + flightType;
            } else {
                indicator.style.display = 'none';
            }
        }
        
        function addRouteSegment() {
            const routeSegments = document.getElementById('routeSegments');
            const newSegment = document.createElement('div');
            newSegment.className = 'route-segment';
            newSegment.setAttribute('data-segment', routeCount + 1);
            
            const segmentType = routeCount === 1 ? 'Outbound' : (routeCount === 2 ? 'Return' : 'Additional');
            
            newSegment.innerHTML = `
                <div class="route-header">
                    <h3 class="segment-title">Segment ${routeCount + 1} - ${segmentType}</h3>
                    <button type="button" class="remove-route" onclick="this.parentElement.parentElement.remove()">
                        Remove
                    </button>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Departure City</label>
                        <input type="text" name="routes[${routeCount}][departure_city]" required placeholder="e.g., London">
                    </div>
                    <div class="form-group">
                        <label class="required">Arrival City</label>
                        <input type="text" name="routes[${routeCount}][arrival_city]" required placeholder="e.g., Dubai">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="required">Departure Date & Time</label>
                        <input type="datetime-local" name="routes[${routeCount}][departure_time]" required 
                               min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label class="required">Arrival Date & Time</label>
                        <input type="datetime-local" name="routes[${routeCount}][arrival_time]" required 
                               min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                
                <div class="segment-details">
                    <div class="form-group">
                        <label class="required">Flight Number</label>
                        <input type="text" name="routes[${routeCount}][flight_number]" required placeholder="e.g., BA456">
                    </div>
                    <div class="form-group">
                        <label class="required">Baggage Allowance</label>
                        <select name="routes[${routeCount}][baggage_allowance]" required>
                            <option value="15kg">15kg</option>
                            <option value="20kg" selected>20kg</option>
                            <option value="25kg">25kg</option>
                            <option value="30kg">30kg</option>
                            <option value="2 pieces">2 pieces</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Meal Included</label>
                        <select name="routes[${routeCount}][meal_included]" required>
                            <option value="no">No</option>
                            <option value="yes" selected>Yes</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Stop Duration (minutes)</label>
                    <input type="number" name="routes[${routeCount}][stop_duration]" value="0" min="0" placeholder="Optional">
                </div>
            `;
            
            routeSegments.appendChild(newSegment);
            routeCount++;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFlightTypeFields();
            
            const priceInputs = document.querySelectorAll('input[name="price"]');
            priceInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if(this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            });

            // Set current datetime as default for datetime inputs
            const now = new Date();
            const localDateTime = now.toISOString().slice(0, 16);
            document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
                if (!input.value) {
                    input.value = localDateTime;
                }
            });
        });
    </script>
</body>
</html>