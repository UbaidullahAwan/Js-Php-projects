<?php
// cron/check_pending_bookings.php
include '../config.php';

// Find bookings older than 3 hours with pending payment
$sql = "SELECT b.booking_id, b.user_id, b.booking_reference, b.total_amount, f.flight_id, f.available_seats, bs.passenger_count 
        FROM bookings b
        JOIN booking_segments bs ON b.booking_id = bs.booking_id
        JOIN flight_routes fr ON bs.route_id = fr.route_id
        JOIN flights f ON fr.flight_id = f.flight_id
        WHERE b.booking_status = 'on_hold' 
        AND b.payment_status = 'pending'
        AND b.booking_date < DATE_SUB(NOW(), INTERVAL 3 HOUR)";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$expired_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($expired_bookings as $booking) {
    try {
        $pdo->beginTransaction();

        // Update booking status
        $update_booking = "UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = ?";
        $pdo->prepare($update_booking)->execute([$booking['booking_id']]);

        // Restore seats
        $restore_seats = "UPDATE flights SET available_seats = available_seats + ? WHERE flight_id = ?";
        $pdo->prepare($restore_seats)->execute([$booking['passenger_count'], $booking['flight_id']]);

        // Generate cancellation PDF
        generateCancellationPDF($booking['booking_id'], $booking['booking_reference']);

        // Send cancellation email
        sendCancellationEmail($booking['user_id'], $booking['booking_id'], $booking['booking_reference']);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Failed to cancel booking {$booking['booking_id']}: " . $e->getMessage());
    }
}

function generateCancellationPDF($booking_id, $booking_reference) {
    // Similar to generateBookingPDF but with cancellation status
    // You can modify your PDF template to show cancellation status
    // Save as "booking_{$booking_reference}_cancelled.pdf"
}

function sendCancellationEmail($user_id, $booking_id, $booking_reference) {
    global $pdo;
    
    $user_sql = "SELECT * FROM users WHERE Id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $to = $user['email'];
    $subject = "Booking Cancelled - {$booking_reference}";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background: #e74c3c; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Booking Cancelled</h1>
            <p>Reference: <strong>{$booking_reference}</strong></p>
        </div>
        <div class='content'>
            <h2>Dear " . $user['first_name'] . " " . $user['last_name'] . ",</h2>
            <p>Your booking <strong>#{$booking_reference}</strong> has been cancelled because payment was not received within the 3-hour time limit.</p>
            <p>The seats have been released and are now available for other passengers.</p>
            <p>If you still wish to book this flight, please visit our website and make a new booking.</p>
            <p>We hope to serve you in the future.</p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: bookings@yourwebsite.com" . "\r\n";

    mail($to, $subject, $message, $headers);
}
?>