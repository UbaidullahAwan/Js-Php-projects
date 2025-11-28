<?php
session_start();
include 'config.php';

// Check if user is logged in and has booking data
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

if(!isset($_SESSION['booking_data'])) {
    header('Location: flights.php');
    exit;
}

$booking_data = $_SESSION['booking_data'];
$user_name = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Airline System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add your CSS styles */
        .payment-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="payment-container">
            <h1><i class="fas fa-credit-card"></i> Payment Page</h1>
            
            <div class="success-message">
                <h3>Booking Successful!</h3>
                <p>Booking Reference: <strong><?php echo $booking_data['booking_reference']; ?></strong></p>
                <p>Total Amount: <strong>PKR <?php echo number_format($booking_data['total_amount'], 0); ?></strong></p>
            </div>
            
            <h2>Payment Methods</h2>
            <p>This is a demo payment page. In a real system, you would integrate with payment gateways here.</p>
            
            <div style="margin-top: 20px;">
                <a href="flights.php" class="btn btn-primary">Back to Flights</a>
                <a href="bookings.php" class="btn btn-secondary">View My Bookings</a>
            </div>
        </div>
    </div>
</body>
</html>