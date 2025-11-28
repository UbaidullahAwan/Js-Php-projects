<?php
session_start();
include 'config.php';

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$booking_ref = isset($_GET['booking_ref']) ? $_GET['booking_ref'] : '';

if(empty($booking_ref)) {
    header('Location: user_dashboard.php');
    exit;
}

// Verify user owns this booking
try {
    $sql = "SELECT user_id FROM bookings WHERE booking_reference = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$booking_ref]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$booking || $booking['user_id'] != $_SESSION['user_id']) {
        die('Access denied or booking not found.');
    }
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Resend email
require_once 'send_ticket_email.php';
$email_sent = sendBookingConfirmationEmail($booking_ref);

// Store result in session
$_SESSION['email_resent'] = $email_sent;
$_SESSION['email_resent_ref'] = $booking_ref;

// Redirect back to success page or dashboard
header('Location: booking_success.php?booking_ref=' . $booking_ref);
exit;
?>