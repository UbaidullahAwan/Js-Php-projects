<?php
session_start();
if(!isset($_SESSION['booking_complete'])) {
    header('Location: flights.php');
    exit;
}

$booking_id = $_SESSION['booking_id'];
$booking_reference = $_SESSION['booking_reference'];

// Clear the session
unset($_SESSION['booking_complete']);
unset($_SESSION['booking_id']);
unset($_SESSION['booking_reference']);
unset($_SESSION['booking_data']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmation</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
            background: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 20px; 
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .warning { 
            background: #fff3cd; 
            color: #856404; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px;
        }
        .btn {
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px;
        }
        .reference {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">
            <h1>✅ Booking Received!</h1>
            <div class="reference">Reference: <?php echo $booking_reference; ?></div>
            <p>We've sent a confirmation email with your booking details.</p>
        </div>
        
        <div class="warning">
            <h2>⚠️ Important Notice</h2>
            <p>You have <strong>3 hours</strong> to complete your payment.</p>
            <p>Check your email for the booking confirmation and payment link.</p>
            <p><strong>Payment Deadline:</strong> <?php echo date('Y-m-d H:i:s', strtotime('+3 hours')); ?></p>
        </div>

        <div>
            <a href="payment.php?booking_id=<?php echo $booking_id; ?>" class="btn">
                Proceed to Payment
            </a>
            <a href="flights.php" class="btn" style="background: #95a5a6;">
                Back to Flights
            </a>
        </div>
        
        <p style="margin-top: 20px; color: #7f8c8d;">
            If you don't see the email, please check your spam folder.
        </p>
    </div>
</body>
</html>