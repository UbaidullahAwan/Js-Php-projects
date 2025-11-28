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

// Get flight_id from URL
$flight_id = isset($_GET['flight_id']) ? intval($_GET['flight_id']) : 0;

if($flight_id == 0) {
    die('Invalid flight selection. No flight ID provided.');
}

// Fetch flight details
try {
    $sql = "SELECT f.*, a.airline_name, a.airline_code, a.logo_path
            FROM flights f 
            LEFT JOIN airlines a ON f.airline_id = a.airline_id 
            WHERE f.flight_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$flight_id]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$flight) {
        die('Flight not found.');
    }
    
    // Get route segments
    $route_sql = "SELECT * FROM flight_routes WHERE flight_id = ? ORDER BY segment_order";
    $route_stmt = $pdo->prepare($route_sql);
    $route_stmt->execute([$flight_id]);
    $route_info = $route_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $available_seats = $flight['available_seats'] ?? 0;
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle form submission for booking confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $num_adults = intval($_POST['num_adults'] ?? 1);
    $total_amount = $flight['price'] * $num_adults;
    
    // Generate booking reference
    $booking_reference = 'BK' . strtoupper(uniqid());
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Debug: Check what columns exist in bookings table
        $check_columns = $pdo->query("DESCRIBE bookings")->fetchAll(PDO::FETCH_COLUMN);
        
        // Create booking record - adjust based on actual column names
        if (in_array('flight_id', $check_columns)) {
            // If flight_id column exists
            $booking_sql = "INSERT INTO bookings (user_id, flight_id, booking_reference, num_passengers, total_amount, booking_status, booking_date) 
                           VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())";
            $booking_stmt = $pdo->prepare($booking_sql);
            $booking_stmt->execute([$user_id, $flight_id, $booking_reference, $num_adults, $total_amount]);
        } else {
            // If flight_id column doesn't exist, try without it or use a different column name
            $booking_sql = "INSERT INTO bookings (user_id, booking_reference, num_passengers, total_amount, booking_status, booking_date) 
                           VALUES (?, ?, ?, ?, 'confirmed', NOW())";
            $booking_stmt = $pdo->prepare($booking_sql);
            $booking_stmt->execute([$user_id, $booking_reference, $num_adults, $total_amount]);
        }
        
        $booking_id = $pdo->lastInsertId();
        
        // Store flight_id in a separate table if needed, or use session
        if (!in_array('flight_id', $check_columns)) {
            // If bookings table doesn't have flight_id, store it in a separate table or session
            $flight_booking_sql = "INSERT INTO booking_flights (booking_id, flight_id) VALUES (?, ?)";
            $flight_booking_stmt = $pdo->prepare($flight_booking_sql);
            $flight_booking_stmt->execute([$booking_id, $flight_id]);
        }
        
        // Collect and store passenger data
        $passengerData = [];
        for($i = 1; $i <= $num_adults; $i++) {
            $passengerData[] = [
                'title' => $_POST['passenger_title_' . $i] ?? '',
                'full_name' => $_POST['passenger_name_' . $i] ?? '',
                'passport_number' => $_POST['passport_number_' . $i] ?? '',
                'passport_expiry' => $_POST['passport_expiry_' . $i] ?? '',
                'date_of_birth' => $_POST['date_of_birth_' . $i] ?? '',
                'nationality' => $_POST['nationality_' . $i] ?? '',
                'gender' => $_POST['gender_' . $i] ?? ''
            ];
            
            // Check passengers table structure
            $passenger_columns = $pdo->query("DESCRIBE passengers")->fetchAll(PDO::FETCH_COLUMN);
            
            // Insert passenger into database
            if (in_array('booking_id', $passenger_columns)) {
                $passenger_sql = "INSERT INTO passengers (booking_id, title, full_name, passport_number, passport_expiry, date_of_birth, nationality, gender) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $passenger_stmt = $pdo->prepare($passenger_sql);
                $passenger_stmt->execute([
                    $booking_id,
                    $_POST['passenger_title_' . $i],
                    $_POST['passenger_name_' . $i],
                    $_POST['passport_number_' . $i],
                    $_POST['passport_expiry_' . $i],
                    $_POST['date_of_birth_' . $i],
                    $_POST['nationality_' . $i],
                    $_POST['gender_' . $i]
                ]);
            } else {
                // Alternative approach if booking_id doesn't exist in passengers table
                $passenger_sql = "INSERT INTO passengers (title, full_name, passport_number, passport_expiry, date_of_birth, nationality, gender) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)";
                $passenger_stmt = $pdo->prepare($passenger_sql);
                $passenger_stmt->execute([
                    $_POST['passenger_title_' . $i],
                    $_POST['passenger_name_' . $i],
                    $_POST['passport_number_' . $i],
                    $_POST['passport_expiry_' . $i],
                    $_POST['date_of_birth_' . $i],
                    $_POST['nationality_' . $i],
                    $_POST['gender_' . $i]
                ]);
            }
        }
        
        // Update available seats
        $update_seats_sql = "UPDATE flights SET available_seats = available_seats - ? WHERE flight_id = ?";
        $update_seats_stmt = $pdo->prepare($update_seats_sql);
        $update_seats_stmt->execute([$num_adults, $flight_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Send confirmation email
        sendBookingConfirmationEmail($user_email, $user_name, $booking_reference, $flight, $num_adults, $total_amount, $passengerData);
        
        // Store in session for payment
        $_SESSION['booking_data'] = [
            'booking_id' => $booking_id,
            'booking_reference' => $booking_reference,
            'flight_id' => $flight_id,
            'flight_data' => $flight,
            'num_adults' => $num_adults,
            'total_amount' => $total_amount,
            'passenger_data' => $passengerData,
            'route_info' => $route_info
        ];
        
        // Redirect to payment page
        header('Location: payment.php');
        exit;
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        die('Booking failed: ' . $e->getMessage());
    }
}

// Function to send booking confirmation email
function sendBookingConfirmationEmail($user_email, $user_name, $booking_reference, $flight, $num_adults, $total_amount, $passengerData) {
    $subject = "Flight Booking Confirmation - " . $booking_reference;
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background: #3498db; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .passenger-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .passenger-table th, .passenger-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .passenger-table th { background: #f2f2f2; }
            .total { font-size: 18px; font-weight: bold; color: #27ae60; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Flight Booking Confirmation</h1>
            <p>Booking Reference: <strong>$booking_reference</strong></p>
        </div>
        <div class='content'>
            <h2>Hello $user_name,</h2>
            <p>Your flight booking has been confirmed. Here are your booking details:</p>
            
            <div class='booking-details'>
                <h3>Flight Information</h3>
                <p><strong>Airline:</strong> {$flight['airline_name']}</p>
                <p><strong>Flight Number:</strong> {$flight['flight_number']}</p>
                <p><strong>Departure:</strong> {$flight['departure_city']} to {$flight['arrival_city']}</p>
                <p><strong>Passengers:</strong> $num_adults Adult(s)</p>
                <p class='total'>Total Amount: PKR " . number_format($total_amount, 0) . "</p>
            </div>
            
            <h3>Passenger Details</h3>
            <table class='passenger-table'>
                <tr>
                    <th>Name</th>
                    <th>Passport Number</th>
                    <th>Date of Birth</th>
                    <th>Nationality</th>
                </tr>
    ";
    
    foreach($passengerData as $passenger) {
        $message .= "
            <tr>
                <td>{$passenger['title']} {$passenger['full_name']}</td>
                <td>{$passenger['passport_number']}</td>
                <td>{$passenger['date_of_birth']}</td>
                <td>{$passenger['nationality']}</td>
            </tr>
        ";
    }
    
    $message .= "
            </table>
            
            <p><strong>Next Step:</strong> Please proceed to payment to complete your booking.</p>
            <p>If you have any questions, please contact our customer support.</p>
            
            <p>Best regards,<br>Airline Booking System Team</p>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: bookings@airlinesystem.com" . "\r\n";
    $headers .= "Reply-To: noreply@airlinesystem.com" . "\r\n";
    
    // Send email
    mail($user_email, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Flight</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain exactly the same */
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

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px;
            background: #f8f9fa;
            min-height: 100vh;
            width: calc(100% - 250px);
        }

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

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            border: 3px solid #e1e8ed;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            padding: 10px 0;
            min-width: 200px;
            display: none;
            z-index: 1000;
            border: 1px solid #e1e8ed;
        }

        .profile-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: #2c3e50;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-divider {
            height: 1px;
            background: #e1e8ed;
            margin: 5px 0;
        }

        .content-area {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }

        .flight-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
            margin-bottom: 25px;
        }

        .flight-header-compact {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .airline-logo-compact {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 8px;
        }

        .flight-basic-info-compact {
            flex: 1;
        }

        .airline-name-compact {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
        }

        .flight-number-compact {
            color: #7f8c8d;
            font-size: 14px;
        }

        .route-accordion {
            margin-top: 15px;
        }

        .accordion-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e1e8ed;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .accordion-header:hover {
            background: #e8f4fd;
        }

        .accordion-header.active .accordion-icon i {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .accordion-content.open {
            max-height: 500px;
        }

        .route-segments {
            margin-top: 15px;
        }

        .route-segment {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e1e8ed;
        }

        .segment-number {
            width: 30px;
            height: 30px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .segment-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .segment-city {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .city-name {
            font-weight: 700;
            font-size: 16px;
            color: #2c3e50;
        }

        .city-time {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .segment-arrow {
            color: #3498db;
            font-size: 18px;
        }

        .layover-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 8px 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 12px;
            color: #856404;
        }

        .passengers-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .passenger-controls {
            display: flex;
            gap: 20px;
            align-items: end;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e8ed;
        }

        .control-group {
            flex: 1;
        }

        .control-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .control-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .passenger-repeater {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .passenger-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e1e8ed;
        }

        .passenger-header {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .form-input, .form-select {
            padding: 10px 12px;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .summary-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
            margin-top: 25px;
        }

        .price-breakdown {
            margin-bottom: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .price-total {
            border-top: 2px solid #3498db;
            border-bottom: none;
            padding-top: 15px;
            margin-top: 10px;
        }

        .total-label {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
        }

        .total-value {
            font-weight: 700;
            font-size: 18px;
            color: #27ae60;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 15px 30px;
            font-size: 16px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #95a5a6;
            color: #95a5a6;
        }

        .btn-outline:hover {
            background: #95a5a6;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #7f8c8d;
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #e74c3c;
        }

        .modal-body {
            padding: 25px;
        }

        .review-section {
            margin-bottom: 25px;
        }

        .review-section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .passenger-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .passenger-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e1e8ed;
        }

        .passenger-card-header {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .passenger-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e1e8ed;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .passenger-controls {
                flex-direction: column;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .passenger-details {
                grid-template-columns: 1fr;
            }
            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-bar">
                <div></div>
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

            <div class="content-area">
                <div class="page-header">
                    <h1 class="page-title">Complete Your Booking</h1>
                    <p class="page-subtitle">Enter passenger information</p>
                </div>

                <!-- Flight Section -->
                <div class="flight-section">
                    <div class="flight-header-compact">
                        <?php if(!empty($flight['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($flight['logo_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($flight['airline_name']); ?>" 
                                 class="airline-logo-compact">
                        <?php else: ?>
                            <div style="font-size: 24px; color: #3498db; font-weight: bold; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <?php echo substr($flight['airline_name'], 0, 2); ?>
                            </div>
                        <?php endif; ?>
                        <div class="flight-basic-info-compact">
                            <div class="airline-name-compact"><?php echo htmlspecialchars($flight['airline_name']); ?></div>
                            <div class="flight-number-compact">Flight <?php echo htmlspecialchars($flight['flight_number']); ?></div>
                        </div>
                    </div>

                    <?php if(!empty($route_info)): ?>
                    <div class="route-accordion">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <div class="accordion-title">
                                <i class="fas fa-route"></i>
                                View Route Details
                                <span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px;">
                                    <?php echo count($route_info); ?> segment<?php echo count($route_info) > 1 ? 's' : ''; ?>
                                </span>
                            </div>
                            <div class="accordion-icon">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="accordion-content">
                            <div class="route-segments">
                                <?php foreach($route_info as $index => $route): ?>
                                    <div class="route-segment">
                                        <div class="segment-number"><?php echo $index + 1; ?></div>
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
                                    <?php if($index < count($route_info) - 1 && !empty($route['stop_duration'])): ?>
                                        <div class="layover-info">
                                            <i class="fas fa-clock"></i>
                                            Layover: <?php echo $route['stop_duration']; ?> in <?php echo htmlspecialchars($route['arrival_city']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Passengers Section -->
                <form method="POST" action="bookings.php?flight_id=<?php echo $flight_id; ?>" id="bookingForm">
                    <div class="passengers-section">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i>
                            Passenger Information
                        </h3>

                        <div class="passenger-controls">
                            <div class="control-group">
                                <label class="control-label">Number of Adults</label>
                                <select name="num_adults" id="numAdults" class="control-input" onchange="updatePassengerForms()">
                                    <?php for($i = 1; $i <= $available_seats; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Adult<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Price per Adult</label>
                                <input type="text" class="control-input" value="PKR <?php echo number_format($flight['price'], 0); ?>" readonly>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Available Seats</label>
                                <input type="text" class="control-input" value="<?php echo $available_seats; ?> seats" readonly>
                            </div>
                        </div>

                        <!-- Form for passenger data -->
                        <div id="passengerRepeater">
                            <!-- Passenger forms will be generated here by JavaScript -->
                        </div>

                        <div class="summary-section">
                            <h3 class="section-title">
                                <i class="fas fa-receipt"></i>
                                Booking Summary
                            </h3>
                            
                            <div class="price-breakdown">
                                <div class="price-item">
                                    <span class="price-label">Price per Adult</span>
                                    <span class="price-value">PKR <?php echo number_format($flight['price'], 0); ?></span>
                                </div>
                                <div class="price-item">
                                    <span class="price-label">Number of Adults</span>
                                    <span class="price-value" id="summaryAdults">1</span>
                                </div>
                                <div class="price-item price-total">
                                    <span class="total-label">Total Amount</span>
                                    <span class="total-value" id="totalAmount">PKR <?php echo number_format($flight['price'], 0); ?></span>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <a href="flights.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Flights
                                </a>
                                <button type="button" class="btn btn-primary" id="reviewButton" onclick="validateAndShowModal()">
                                    <i class="fas fa-eye"></i>
                                    Review Booking
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Review Booking Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-clipboard-check"></i>
                    Review Your Booking
                </h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">
                    <i class="fas fa-edit"></i>
                    Edit Details
                </button>
                <button type="button" class="btn btn-success" onclick="submitBooking()">
                    <i class="fas fa-credit-card"></i>
                    Confirm & Proceed to Payment
                </button>
            </div>
        </div>
    </div>

    <script>
        // Profile dropdown functionality
        document.getElementById('userAvatar').addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.toggle('show');
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.getElementById('userAvatar');
            if (!avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Accordion functionality
        function toggleAccordion(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('active');
            content.classList.toggle('open');
            
            if (content.classList.contains('open')) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
            }
        }

        // Modal functionality
        function openModal() {
            document.getElementById('reviewModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('reviewModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Update passenger forms and summary
        function updatePassengerForms() {
            const numAdults = parseInt(document.getElementById('numAdults').value);
            const passengerRepeater = document.getElementById('passengerRepeater');
            const pricePerAdult = <?php echo $flight['price']; ?>;
            
            // Update summary
            document.getElementById('summaryAdults').textContent = numAdults;
            document.getElementById('totalAmount').textContent = 'PKR ' + (pricePerAdult * numAdults).toLocaleString();
            
            // Generate passenger forms
            passengerRepeater.innerHTML = '';
            
            for(let i = 1; i <= numAdults; i++) {
                const passengerForm = document.createElement('div');
                passengerForm.className = 'passenger-form';
                passengerForm.innerHTML = `
                    <div class="passenger-header">
                        <i class="fas fa-user"></i>
                        Passenger ${i} ${i === 1 ? '(Primary)' : ''}
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Title *</label>
                            <select name="passenger_title_${i}" class="form-select" required>
                                <option value="">Select Title</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Miss">Miss</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                            <div class="error-message" id="error_title_${i}">Please select a title</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="passenger_name_${i}" class="form-input" required 
                                   placeholder="Enter full name">
                            <div class="error-message" id="error_name_${i}">Please enter full name</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <select name="gender_${i}" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="error-message" id="error_gender_${i}">Please select gender</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Passport Number *</label>
                            <input type="text" name="passport_number_${i}" class="form-input" required 
                                   placeholder="Enter passport number">
                            <div class="error-message" id="error_passport_${i}">Please enter passport number</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Passport Expiry *</label>
                            <input type="date" name="passport_expiry_${i}" class="form-input" required>
                            <div class="error-message" id="error_passport_expiry_${i}">Please select passport expiry date</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="date_of_birth_${i}" class="form-input" required>
                            <div class="error-message" id="error_dob_${i}">Please select date of birth</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nationality *</label>
                            <input type="text" name="nationality_${i}" class="form-input" required 
                                   placeholder="Enter nationality">
                            <div class="error-message" id="error_nationality_${i}">Please enter nationality</div>
                        </div>
                    </div>
                `;
                passengerRepeater.appendChild(passengerForm);
            }
        }

        // Function to validate form and show modal
        function validateAndShowModal() {
            const numAdults = parseInt(document.getElementById('numAdults').value);
            let isValid = true;
            
            // Hide all error messages first
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });
            
            // Validate each passenger
            for(let i = 1; i <= numAdults; i++) {
                const fields = [
                    { name: `passenger_title_${i}`, errorId: `error_title_${i}` },
                    { name: `passenger_name_${i}`, errorId: `error_name_${i}` },
                    { name: `gender_${i}`, errorId: `error_gender_${i}` },
                    { name: `passport_number_${i}`, errorId: `error_passport_${i}` },
                    { name: `passport_expiry_${i}`, errorId: `error_passport_expiry_${i}` },
                    { name: `date_of_birth_${i}`, errorId: `error_dob_${i}` },
                    { name: `nationality_${i}`, errorId: `error_nationality_${i}` }
                ];
                
                fields.forEach(field => {
                    const input = document.querySelector(`[name="${field.name}"]`);
                    if (input && !input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = '#e74c3c';
                        document.getElementById(field.errorId).style.display = 'block';
                    } else if (input) {
                        input.style.borderColor = '#e1e8ed';
                    }
                });
            }
            
            if (isValid) {
                updateModalContent();
                openModal();
            } else {
                alert('Please fill in all required fields for all passengers.');
            }
        }

        // Function to update modal content with current form data
        function updateModalContent() {
            const numAdults = parseInt(document.getElementById('numAdults').value);
            const pricePerAdult = <?php echo $flight['price']; ?>;
            const totalAmount = pricePerAdult * numAdults;
            
            const modalBody = document.getElementById('modalBodyContent');
            
            let modalContent = `
                <!-- Flight Details -->
                <div class="review-section">
                    <h3 class="review-section-title">
                        <i class="fas fa-plane"></i>
                        Flight Details
                    </h3>
                    <div class="flight-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                            <?php if(!empty($flight['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($flight['logo_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($flight['airline_name']); ?>" 
                                     style="width: 40px; height: 40px; object-fit: contain; border-radius: 6px;">
                            <?php else: ?>
                                <div style="font-size: 18px; color: #3498db; font-weight: bold; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <?php echo substr($flight['airline_name'], 0, 2); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight: 700; color: #2c3e50;"><?php echo htmlspecialchars($flight['airline_name']); ?></div>
                                <div style="color: #7f8c8d; font-size: 14px;">Flight <?php echo htmlspecialchars($flight['flight_number']); ?></div>
                            </div>
                        </div>
                        <div style="color: #27ae60; font-weight: 700; font-size: 18px;">
                            PKR ${totalAmount.toLocaleString()}
                        </div>
                    </div>
                </div>

                <!-- Passenger Details -->
                <div class="review-section">
                    <h3 class="review-section-title">
                        <i class="fas fa-users"></i>
                        Passenger Details (${numAdults})
                    </h3>
                    <div class="passenger-list">
            `;
            
            for(let i = 1; i <= numAdults; i++) {
                const title = document.querySelector(`[name="passenger_title_${i}"]`).value;
                const name = document.querySelector(`[name="passenger_name_${i}"]`).value;
                const gender = document.querySelector(`[name="gender_${i}"]`).value;
                const passport = document.querySelector(`[name="passport_number_${i}"]`).value;
                const passportExpiry = document.querySelector(`[name="passport_expiry_${i}"]`).value;
                const dob = document.querySelector(`[name="date_of_birth_${i}"]`).value;
                const nationality = document.querySelector(`[name="nationality_${i}"]`).value;
                
                // Format dates
                const formatDate = (dateString) => {
                    if (!dateString) return 'Not set';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                };
                
                modalContent += `
                    <div class="passenger-card">
                        <div class="passenger-card-header">
                            <i class="fas fa-user"></i>
                            Passenger ${i} ${i === 1 ? '(Primary)' : ''}
                        </div>
                        <div class="passenger-details">
                            <div class="detail-item">
                                <span class="detail-label">Title & Name</span>
                                <span class="detail-value">${title} ${name}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Gender</span>
                                <span class="detail-value">${gender}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Date of Birth</span>
                                <span class="detail-value">${formatDate(dob)}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Nationality</span>
                                <span class="detail-value">${nationality}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Passport Number</span>
                                <span class="detail-value">${passport}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Passport Expiry</span>
                                <span class="detail-value">${formatDate(passportExpiry)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            modalContent += `
                    </div>
                </div>

                <!-- Price Summary -->
                <div class="review-section">
                    <h3 class="review-section-title">
                        <i class="fas fa-receipt"></i>
                        Price Summary
                    </h3>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e1e8ed;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e1e8ed;">
                            <span>Price per Adult</span>
                            <span>PKR ${pricePerAdult.toLocaleString()}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e1e8ed;">
                            <span>Number of Adults</span>
                            <span>${numAdults}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0; font-size: 18px; font-weight: 700; color: #27ae60;">
                            <span>Total Amount</span>
                            <span>PKR ${totalAmount.toLocaleString()}</span>
                        </div>
                    </div>
                </div>
            `;
            
            modalBody.innerHTML = modalContent;
        }

        // Function to submit booking and proceed to payment
        function submitBooking() {
            // Add the confirm_booking hidden input
            const confirmInput = document.createElement('input');
            confirmInput.type = 'hidden';
            confirmInput.name = 'confirm_booking';
            confirmInput.value = 'true';
            document.getElementById('bookingForm').appendChild(confirmInput);
            
            // Submit the form
            document.getElementById('bookingForm').submit();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePassengerForms();
            
            // Open first accordion by default
            const firstAccordion = document.querySelector('.accordion-content');
            if (firstAccordion) {
                firstAccordion.style.maxHeight = firstAccordion.scrollHeight + 'px';
                firstAccordion.previousElementSibling.classList.add('active');
            }
        });
    </script>
</body>
</html>