<?php
session_start();
include 'config.php';

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get filter parameters
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$airline_filter = isset($_GET['airline']) ? $_GET['airline'] : '';
$price_range = isset($_GET['price_range']) ? $_GET['price_range'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'price_asc';
$flight_type = isset($_GET['flight_type']) ? $_GET['flight_type'] : '';

// Build flight query with routes
$sql = "SELECT 
            f.*, 
            a.airline_name, 
            a.airline_code, 
            a.logo_path as airline_logo,
            GROUP_CONCAT(fr.route_id ORDER BY fr.segment_order) as route_ids,
            GROUP_CONCAT(fr.departure_city ORDER BY fr.segment_order) as departure_cities,
            GROUP_CONCAT(fr.arrival_city ORDER BY fr.segment_order) as arrival_cities,
            GROUP_CONCAT(fr.departure_time ORDER BY fr.segment_order) as departure_times,
            GROUP_CONCAT(fr.arrival_time ORDER BY fr.segment_order) as arrival_times,
            GROUP_CONCAT(fr.segment_order ORDER BY fr.segment_order) as segment_orders,
            GROUP_CONCAT(fr.stop_duration ORDER BY fr.segment_order) as stop_durations,
            GROUP_CONCAT(fr.flight_number ORDER BY fr.segment_order) as route_flight_numbers
        FROM flights f 
        JOIN airlines a ON f.airline_id = a.airline_id 
        LEFT JOIN flight_routes fr ON f.flight_id = fr.flight_id
        WHERE f.status = 'active' AND f.available_seats > 0";

$params = [];

// Add search filter
if(!empty($search_query)) {
    $sql .= " AND (f.flight_number LIKE ? OR a.airline_name LIKE ? OR fr.departure_city LIKE ? OR fr.arrival_city LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add airline filter
if(!empty($airline_filter)) {
    $sql .= " AND a.airline_id = ?";
    $params[] = $airline_filter;
}

// Add flight type filter
if(!empty($flight_type)) {
    $sql .= " AND f.flight_type = ?";
    $params[] = $flight_type;
}

// Add price range filter
if(!empty($price_range)) {
    switch($price_range) {
        case '0-500':
            $sql .= " AND f.price BETWEEN 0 AND 500";
            break;
        case '500-1000':
            $sql .= " AND f.price BETWEEN 500 AND 1000";
            break;
        case '1000-2000':
            $sql .= " AND f.price BETWEEN 1000 AND 2000";
            break;
        case '2000+':
            $sql .= " AND f.price >= 2000";
            break;
    }
}

$sql .= " GROUP BY f.flight_id";

// Add sorting
switch($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY f.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY f.price DESC";
        break;
    case 'seats_asc':
        $sql .= " ORDER BY f.available_seats ASC";
        break;
    case 'seats_desc':
        $sql .= " ORDER BY f.available_seats DESC";
        break;
    case 'airline_asc':
        $sql .= " ORDER BY a.airline_name ASC";
        break;
    case 'departure_asc':
        $sql .= " ORDER BY MIN(fr.departure_time) ASC";
        break;
    default:
        $sql .= " ORDER BY f.flight_id DESC";
}

// Get flights
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process flights data to organize routes
foreach($flights as &$flight) {
    $flight['routes'] = [];
    
    if(!empty($flight['route_ids'])) {
        $route_ids = explode(',', $flight['route_ids']);
        $departure_cities = explode(',', $flight['departure_cities']);
        $arrival_cities = explode(',', $flight['arrival_cities']);
        $departure_times = explode(',', $flight['departure_times']);
        $arrival_times = explode(',', $flight['arrival_times']);
        $segment_orders = explode(',', $flight['segment_orders']);
        $stop_durations = explode(',', $flight['stop_durations']);
        $route_flight_numbers = explode(',', $flight['route_flight_numbers']);
        
        for($i = 0; $i < count($route_ids); $i++) {
            $flight['routes'][] = [
                'route_id' => $route_ids[$i],
                'departure_city' => $departure_cities[$i],
                'arrival_city' => $arrival_cities[$i],
                'departure_time' => $departure_times[$i],
                'arrival_time' => $arrival_times[$i],
                'segment_order' => $segment_orders[$i],
                'stop_duration' => $stop_durations[$i],
                'flight_number' => $route_flight_numbers[$i] ?? $flight['flight_number']
            ];
        }
    }
    
    // Sort routes by segment order
    usort($flight['routes'], function($a, $b) {
        return $a['segment_order'] - $b['segment_order'];
    });
}
unset($flight);

// Get airlines for filter
$airlines = $pdo->query("SELECT * FROM airlines ORDER BY airline_name")->fetchAll(PDO::FETCH_ASSOC);

// Get user's booking stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM multi_city_bookings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $booking_stats = [
        'total' => 0,
        'confirmed' => 0,
        'processing' => 0,
        'on_hold' => 0,
        'cancelled' => 0,
        'completed' => 0
    ];
}

// If no flights found in database, use sample data
if(empty($flights)) {
    $flights = [
        [
            'flight_id' => 1,
            'flight_number' => 'SV 725',
            'airline_name' => 'Saudia',
            'airline_code' => 'SV',
            'price' => 175000,
            'flight_type' => 'round_trip',
            'baggage_allowance' => '30kg',
            'meal_available' => true,
            'routes' => [
                [
                    'departure_city' => 'ISB',
                    'arrival_city' => 'JED',
                    'departure_time' => '2025-12-13 02:00:00',
                    'arrival_time' => '2025-12-13 05:45:00',
                    'segment_order' => 1,
                    'flight_number' => 'SV 727',
                    'stop_duration' => '2h'
                ],
                [
                    'departure_city' => 'JED',
                    'arrival_city' => 'ISB',
                    'departure_time' => '2026-01-02 02:00:00',
                    'arrival_time' => '2026-01-02 08:45:00',
                    'segment_order' => 2,
                    'flight_number' => 'SV 722',
                    'stop_duration' => '0'
                ]
            ]
        ],
        [
            'flight_id' => 2,
            'flight_number' => 'PK 303',
            'airline_name' => 'PIA',
            'airline_code' => 'PK',
            'price' => 120000,
            'flight_type' => 'one_way',
            'baggage_allowance' => '25kg',
            'meal_available' => true,
            'routes' => [
                [
                    'departure_city' => 'ISB',
                    'arrival_city' => 'DXB',
                    'departure_time' => '2025-12-15 14:00:00',
                    'arrival_time' => '2025-12-15 16:30:00',
                    'segment_order' => 1,
                    'flight_number' => 'PK 303',
                    'stop_duration' => '0'
                ]
            ]
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Flights - Hussain Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #2c3e50;
            line-height: 1.5;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: #ffffff;
            border-right: 1px solid #e1e8ed;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .logo-section {
            padding: 25px 20px;
            border-bottom: 1px solid #e1e8ed;
            text-align: center;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: white;
        }

        .nav-section {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
        }

        .nav-item.active {
            background: #e3f2fd;
            border-left-color: #3498db;
            color: #3498db;
        }

        .nav-item:hover {
            background: #f5f7fa;
            color: #3498db;
        }

        .nav-item i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .nav-item .badge {
            margin-left: auto;
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 11px;
            min-width: 20px;
            text-align: center;
        }

        /* Submenu Styles */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
        }

        .submenu.open {
            max-height: 300px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px 12px 50px;
            color: #7f8c8d;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 13px;
            border-left: 3px solid transparent;
        }

        .submenu-item:hover {
            background: #e3f2fd;
            color: #3498db;
        }

        .submenu-item i {
            width: 16px;
            margin-right: 10px;
            font-size: 12px;
        }

        .submenu-item .badge {
            margin-left: auto;
            background: #e74c3c;
            color: white;
            padding: 1px 5px;
            border-radius: 6px;
            font-size: 10px;
        }

        .logout-section {
            padding: 20px;
            border-top: 1px solid #e1e8ed;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            background: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px;
            background: #f8f9fa;
            min-height: 100vh;
            width: calc(100% - 250px);
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #e1e8ed;
        }

        .search-bar {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #e1e8ed;
            border-radius: 25px;
            font-size: 14px;
            background: #f8f9fa;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-bar button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            background: #2980b9;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .user-email {
            font-size: 11px;
            color: #7f8c8d;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 10px 0;
            min-width: 150px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            border: 1px solid #e1e8ed;
        }

        .profile-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(5px);
        }

        .dropdown-item {
            padding: 10px 15px;
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
            color: #3498db;
        }

        .dropdown-divider {
            height: 1px;
            background: #ecf0f1;
            margin: 5px 0;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .filter-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 13px;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Flight Container */
        .flights-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
            margin-bottom: 25px;
        }

        .flight-group-header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .flight-group-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .flight-group-route {
            font-size: 16px;
            font-weight: 600;
        }

        .flight-group-price {
            font-size: 20px;
            font-weight: 800;
        }

        .flights-table {
            width: 100%;
            border-collapse: collapse;
        }

        .flights-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e1e8ed;
        }

        .flights-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            text-transform: uppercase;
        }

        .flights-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .flights-table tbody tr:hover {
            background: #f8f9fa;
        }

        .flight-date {
            font-weight: 600;
            color: #2c3e50;
            white-space: nowrap;
        }

        .flight-number {
            color: #3498db;
            font-weight: 600;
            font-size: 13px;
        }

        .flight-route {
            font-weight: 600;
            color: #2c3e50;
        }

        .flight-time {
            color: #7f8c8d;
            font-size: 13px;
        }

        .flight-baggage {
            color: #7f8c8d;
            font-size: 13px;
        }

        .flight-meal {
            color: #27ae60;
            font-weight: 600;
            font-size: 13px;
        }

        .flight-price {
            font-weight: 700;
            color: #2c3e50;
            font-size: 16px;
            white-space: nowrap;
        }

        .flight-action {
            text-align: center;
        }

        .btn-book {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .flight-segment {
            border-left: 3px solid #3498db;
        }

        .flight-segment-connector {
            padding-left: 30px;
            position: relative;
        }

        .flight-segment-connector::before {
            content: "↳";
            position: absolute;
            left: 15px;
            color: #3498db;
            font-weight: bold;
        }

        /* Accordion Styles */
        .flight-accordion {
            margin: 10px;
        }

        .accordion-header {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid #e1e8ed;
        }

        .accordion-header:hover {
            background: #e3f2fd;
            border-color: #3498db;
        }

        .accordion-header.active {
            background: #e3f2fd;
            border-color: #3498db;
            border-radius: 8px 8px 0 0;
        }

        .accordion-title {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .accordion-icon {
            transition: transform 0.3s ease;
            font-size: 12px;
            color: #3498db;
        }

        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .route-count {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }

        .accordion-content.open {
            max-height: 1000px;
            border: 1px solid #e1e8ed;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }

        .route-details {
            padding: 20px;
        }

        .route-segment {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #fafbfc;
        }

        .route-segment:last-child {
            margin-bottom: 0;
        }

        .segment-number {
            background: #3498db;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            margin-right: 15px;
        }

        .segment-info {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            align-items: center;
        }

        .segment-city {
            text-align: center;
        }

        .city-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .city-time {
            color: #7f8c8d;
            font-size: 12px;
            font-weight: 500;
        }

        .segment-arrow {
            color: #3498db;
            text-align: center;
            font-size: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .segment-flight {
            text-align: center;
            background: #e3f2fd;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            color: #3498db;
        }

        .layover-info {
            text-align: center;
            padding: 10px;
            background: #fff3cd;
            color: #856404;
            font-size: 12px;
            font-weight: 600;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
        }

        .flight-type-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .one-way { background: #e3f2fd; color: #1976d2; }
        .round-trip { background: #e8f5e8; color: #2e7d32; }
        .multi-city { background: #fff3e0; color: #ef6c00; }

        /* No Flights */
        .no-flights {
            text-align: center;
            padding: 50px 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
        }

        .no-flights i {
            font-size: 50px;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        .no-flights h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .no-flights p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        /* Mobile Styles */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 12px;
            cursor: pointer;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .top-bar {
                padding: 12px 20px;
            }
            
            .search-bar {
                min-width: 75%;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .flights-table {
                display: block;
                overflow-x: auto;
            }
            
            .flight-group-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .mobile-menu-btn {
                display: block;
            }

            .sidebar-overlay.mobile-open {
                display: block;
            }

            .user-info {
                display: none;
            }

            .segment-info {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: center;
            }

            .segment-arrow {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="logo-section">
                <div class="logo">
                   Hussain Group
                </div>
            </div>

            <nav class="nav-section">
                <a href="flights.php" class="nav-item active">
                    <i class="fas fa-plane"></i>
                    Book Flights
                </a>
                
                <div class="nav-item" id="bookingsMenu">
                    <i class="fas fa-suitcase"></i>
                    My Bookings
                    <span class="badge"><?php echo $booking_stats['total'] ?? 0; ?></span>
                    <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 10px;"></i>
                </div>
                
                <div class="submenu" id="bookingsSubmenu">
                    <a href="bookings.php?status=all" class="submenu-item">
                        <i class="fas fa-list"></i>
                        All Bookings
                        <span class="badge"><?php echo $booking_stats['total'] ?? 0; ?></span>
                    </a>
                    <a href="bookings.php?status=processing" class="submenu-item">
                        <i class="fas fa-sync-alt"></i>
                        Processing
                        <span class="badge"><?php echo $booking_stats['processing'] ?? 0; ?></span>
                    </a>
                    <a href="bookings.php?status=on_hold" class="submenu-item">
                        <i class="fas fa-pause-circle"></i>
                        On Hold
                        <span class="badge"><?php echo $booking_stats['on_hold'] ?? 0; ?></span>
                    </a>
                    <a href="bookings.php?status=confirmed" class="submenu-item">
                        <i class="fas fa-check-circle"></i>
                        Confirmed
                        <span class="badge"><?php echo $booking_stats['confirmed'] ?? 0; ?></span>
                    </a>
                    <a href="bookings.php?status=completed" class="submenu-item">
                        <i class="fas fa-flag-checkered"></i>
                        Completed
                        <span class="badge"><?php echo $booking_stats['completed'] ?? 0; ?></span>
                    </a>
                    <a href="bookings.php?status=cancelled" class="submenu-item">
                        <i class="fas fa-times-circle"></i>
                        Cancelled
                        <span class="badge"><?php echo $booking_stats['cancelled'] ?? 0; ?></span>
                    </a>
                </div>

                <a href="ledger.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Ledger
                </a>

                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    My Profile
                </a>
                
                <a href="payments.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    Payments
                </a>
                
                <a href="support.php" class="nav-item">
                    <i class="fas fa-headset"></i>
                    Support
                </a>
            </nav>

            <div class="logout-section">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <form method="GET" action="" id="searchForm">
                        <input type="text" name="search" placeholder="Search flights, airlines, or cities..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <div class="user-profile">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                    <div class="user-avatar" id="userAvatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="bookings.php" class="dropdown-item">
                            <i class="fas fa-suitcase"></i> My Bookings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Find Your Flight</h1>
                    <p class="page-subtitle">Discover the best flight options for your journey</p>
                </div>

                <!-- Filters Section -->
                <div class="filters-section">
                    <form method="GET" action="" id="filterForm">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="filter-label">Airline</label>
                                <select name="airline" class="filter-select" onchange="this.form.submit()">
                                    <option value="">All Airlines</option>
                                    <?php foreach($airlines as $airline): ?>
                                        <option value="<?php echo $airline['airline_id']; ?>" 
                                            <?php echo $airline_filter == $airline['airline_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($airline['airline_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Flight Type</label>
                                <select name="flight_type" class="filter-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="one_way" <?php echo $flight_type == 'one_way' ? 'selected' : ''; ?>>One Way</option>
                                    <option value="round_trip" <?php echo $flight_type == 'round_trip' ? 'selected' : ''; ?>>Round Trip</option>
                                    <option value="multi_city" <?php echo $flight_type == 'multi_city' ? 'selected' : ''; ?>>Multi City</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Price Range</label>
                                <select name="price_range" class="filter-select" onchange="this.form.submit()">
                                    <option value="">Any Price</option>
                                    <option value="0-500" <?php echo $price_range == '0-500' ? 'selected' : ''; ?>>$0 - $500</option>
                                    <option value="500-1000" <?php echo $price_range == '500-1000' ? 'selected' : ''; ?>>$500 - $1,000</option>
                                    <option value="1000-2000" <?php echo $price_range == '1000-2000' ? 'selected' : ''; ?>>$1,000 - $2,000</option>
                                    <option value="2000+" <?php echo $price_range == '2000+' ? 'selected' : ''; ?>>$2,000+</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Sort By</label>
                                <select name="sort_by" class="filter-select" onchange="this.form.submit()">
                                    <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="seats_asc" <?php echo $sort_by == 'seats_asc' ? 'selected' : ''; ?>>Seats: Fewest First</option>
                                    <option value="seats_desc" <?php echo $sort_by == 'seats_desc' ? 'selected' : ''; ?>>Seats: Most First</option>
                                    <option value="airline_asc" <?php echo $sort_by == 'airline_asc' ? 'selected' : ''; ?>>Airline: A to Z</option>
                                    <option value="departure_asc" <?php echo $sort_by == 'departure_asc' ? 'selected' : ''; ?>>Departure: Earliest</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <button type="button" class="btn" onclick="resetFilters()" 
                                        style="background: #95a5a6; color: white;">
                                    <i class="fas fa-redo"></i>
                                    Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Flights List -->
                <div class="flights-list">
                    <?php if(empty($flights)): ?>
                        <div class="no-flights">
                            <i class="fas fa-plane-slash"></i>
                            <h3>No flights found</h3>
                            <p>Try adjusting your search criteria or filters to find more results.</p>
                            <button class="btn-book" onclick="resetFilters()">
                                <i class="fas fa-redo"></i>
                                Reset Filters
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach($flights as $flightIndex => $flight): ?>
                            <div class="flights-container">
                                <!-- Flight Group Header -->
                                <div class="flight-group-header">
                                    <div class="flight-group-info">
                                        <div class="flight-group-route">
                                            <?php 
                                            $routeDisplay = [];
                                            foreach($flight['routes'] as $route) {
                                                $routeDisplay[] = $route['departure_city'] . '-' . $route['arrival_city'];
                                            }
                                            echo implode(' → ', $routeDisplay);
                                            ?>
                                        </div>
                                        <span class="flight-type-badge <?php echo str_replace('_', '-', $flight['flight_type']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $flight['flight_type'])); ?>
                                        </span>
                                    </div>
                                    <div class="flight-group-price">
                                        PKR <?php echo number_format($flight['price'], 0); ?>
                                    </div>
                                </div>

                                <!-- Flights Table -->
                                <table class="flights-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Flight #</th>
                                            <th>Origin-Dest.</th>
                                            <th>Time</th>
                                            <th>Baggage</th>
                                            <th>Meal</th>
                                            <th>Price</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($flight['routes'] as $index => $route): ?>
                                            <tr class="<?php echo $index > 0 ? 'flight-segment-connector' : 'flight-segment'; ?>">
                                                <td class="flight-date">
                                                    <?php echo date('d M Y', strtotime($route['departure_time'])); ?>
                                                </td>
                                                <td class="flight-number">
                                                    <?php 
                                                    $airlineCode = $flight['airline_code'] ?? 'SV';
                                                    $segmentFlightNumber = $route['flight_number'] ?? $flight['flight_number'];
                                                    echo $airlineCode . ' ' . $segmentFlightNumber;
                                                    ?>
                                                </td>
                                                <td class="flight-route">
                                                    <?php echo htmlspecialchars($route['departure_city']) . '-' . htmlspecialchars($route['arrival_city']); ?>
                                                </td>
                                                <td class="flight-time">
                                                    <?php echo date('H:i', strtotime($route['departure_time'])) . '-' . date('H:i', strtotime($route['arrival_time'])); ?>
                                                </td>
                                                <td class="flight-baggage">
                                                    <?php echo $flight['baggage_allowance'] ?? '23+07 KG'; ?>
                                                </td>
                                                <td class="flight-meal">
                                                    <?php echo isset($flight['meal_available']) && $flight['meal_available'] ? 'Yes' : 'No'; ?>
                                                </td>
                                                <td class="flight-price">
                                                    <?php if($index === 0): ?>
                                                        PKR <?php echo number_format($flight['price'], 0); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="flight-action">
                                                    <?php if($index === 0): ?>
                                                        <button class="btn-book" onclick="bookFlight(<?php echo $flight['flight_id']; ?>)">
                                                            <i class="fas fa-ticket-alt"></i>
                                                            Book now
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <!-- Accordion for Route Details -->
                                        <?php if(count($flight['routes']) > 0): ?>
                                        <tr>
                                            <td colspan="8" style="padding: 10px; border: none;">
                                                <div class="flight-accordion">
                                                    <div class="accordion-header <?php echo $flightIndex === 0 ? 'active' : ''; ?>" onclick="toggleAccordion(this)">
                                                        <div class="accordion-title">
                                                            <i class="fas fa-route"></i>
                                                            View Route Details
                                                            <span class="route-count"><?php echo count($flight['routes']); ?> segment<?php echo count($flight['routes']) > 1 ? 's' : ''; ?></span>
                                                        </div>
                                                        <div class="accordion-icon">
                                                            <i class="fas fa-chevron-down"></i>
                                                        </div>
                                                    </div>
                                                    <div class="accordion-content <?php echo $flightIndex === 0 ? 'open' : ''; ?>">
                                                        <div class="route-details">
                                                            <?php foreach($flight['routes'] as $segmentIndex => $route): ?>
                                                                <div class="route-segment">
                                                                    <div class="segment-number"><?php echo $segmentIndex + 1; ?></div>
                                                                    <div class="segment-info">
                                                                        <div class="segment-city">
                                                                            <div class="city-name"><?php echo htmlspecialchars($route['departure_city']); ?></div>
                                                                            <div class="city-time">
                                                                                <?php echo date('H:i', strtotime($route['departure_time'])); ?><br>
                                                                                <?php echo date('d M', strtotime($route['departure_time'])); ?>
                                                                            </div>
                                                                        </div>
                                                                        <div class="segment-arrow">
                                                                            <i class="fas fa-long-arrow-alt-right"></i>
                                                                            <div class="segment-flight">
                                                                                <?php 
                                                                                $airlineCode = $flight['airline_code'] ?? 'SV';
                                                                                $segmentFlightNumber = $route['flight_number'] ?? $flight['flight_number'];
                                                                                echo $airlineCode . ' ' . $segmentFlightNumber;
                                                                                ?>
                                                                            </div>
                                                                        </div>
                                                                        <div class="segment-city">
                                                                            <div class="city-name"><?php echo htmlspecialchars($route['arrival_city']); ?></div>
                                                                            <div class="city-time">
                                                                                <?php echo date('H:i', strtotime($route['arrival_time'])); ?><br>
                                                                                <?php echo date('d M', strtotime($route['arrival_time'])); ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php if($segmentIndex < count($flight['routes']) - 1 && !empty($route['stop_duration'])): ?>
                                                                    <div class="layover-info">
                                                                        <i class="fas fa-clock"></i>
                                                                        Layover: <?php echo $route['stop_duration']; ?> in <?php echo htmlspecialchars($route['arrival_city']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('mobile-open');
        });

        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('mobile-open');
        });

        // User profile dropdown
        document.getElementById('userAvatar').addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.getElementById('userAvatar');
            if (!avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Bookings submenu toggle
        document.getElementById('bookingsMenu').addEventListener('click', function() {
            const submenu = document.getElementById('bookingsSubmenu');
            submenu.classList.toggle('open');
            this.querySelector('.fa-chevron-down').style.transform = submenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)';
        });

        // Accordion functionality
        function toggleAccordion(header) {
            const accordion = header.parentElement;
            const content = accordion.querySelector('.accordion-content');
            
            // Toggle current accordion
            header.classList.toggle('active');
            content.classList.toggle('open');
            
            // Smooth height transition
            if (content.classList.contains('open')) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
            }
        }

        // Initialize first accordion as open
        document.addEventListener('DOMContentLoaded', function() {
            const firstAccordion = document.querySelector('.accordion-content');
            if (firstAccordion) {
                firstAccordion.style.maxHeight = firstAccordion.scrollHeight + 'px';
            }
        });

        // Reset filters
        function resetFilters() {
            window.location.href = 'flights.php';
        }

        // Book flight
        function bookFlight(flightId) {
            window.location.href = `bookings.php?flight_id=${flightId}`;
        }

        // Logout function
        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Auto-submit search form when typing stops
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 1000);
        });
    </script>
</body>
</html>