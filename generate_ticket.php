<?php
session_start();
include 'config.php';
require_once 'vendor/autoload.php'; // For dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$booking_ref = isset($_GET['booking_ref']) ? $_GET['booking_ref'] : '';

if(empty($booking_ref)) {
    die('Booking reference required.');
}

// Fetch complete booking details
try {
    // Fetch booking and flight details
    $booking_sql = "SELECT b.*, f.*, a.airline_name, a.airline_code 
                   FROM bookings b 
                   JOIN flights f ON b.flight_id = f.flight_id 
                   JOIN airlines a ON f.airline_id = a.airline_id 
                   WHERE b.booking_reference = ?";
    
    $booking_stmt = $pdo->prepare($booking_sql);
    $booking_stmt->execute([$booking_ref]);
    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$booking) {
        die('Booking not found.');
    }
    
    // Fetch passenger details
    $passenger_sql = "SELECT * FROM passengers WHERE booking_id = ?";
    $passenger_stmt = $pdo->prepare($passenger_sql);
    $passenger_stmt->execute([$booking['booking_id']]);
    $passengers = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .ticket-container {
            border: 2px solid #333;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .airline-logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .status-badge {
            background: #ffc107;
            color: #856404;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
            margin: 10px 0;
        }
        .booking-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .flight-route {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 8px;
        }
        .route-city {
            text-align: center;
        }
        .city-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .passenger-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .passenger-table th, .passenger-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .passenger-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .barcode {
            text-align: center;
            margin: 20px 0;
            font-family: "Libre Barcode 128", cursive;
            font-size: 36px;
        }
        .important-notice {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="header">
            <div class="airline-logo">' . htmlspecialchars($booking['airline_name']) . '</div>
            <h1>ELECTRONIC TICKET</h1>
            <div class="status-badge">TICKET ON HOLD - PENDING PAYMENT</div>
        </div>

        <div class="booking-info">
            <div>
                <strong>Booking Reference:</strong><br>
                ' . $booking_ref . '
            </div>
            <div>
                <strong>Issue Date:</strong><br>
                ' . date('M d, Y H:i') . '
            </div>
            <div>
                <strong>Flight:</strong><br>
                ' . htmlspecialchars($booking['flight_number']) . '
            </div>
        </div>

        <div class="flight-route">
            <div class="route-city">
                <div class="city-name">' . htmlspecialchars($booking['departure_city']) . '</div>
                <div>' . date('M d, Y', strtotime($booking['departure_date'])) . '</div>
                <div>' . date('H:i', strtotime($booking['departure_time'])) . '</div>
            </div>
            <div style="font-size: 24px;">→</div>
            <div class="route-city">
                <div class="city-name">' . htmlspecialchars($booking['arrival_city']) . '</div>
                <div>' . date('M d, Y', strtotime($booking['arrival_date'])) . '</div>
                <div>' . date('H:i', strtotime($booking['arrival_time'])) . '</div>
            </div>
        </div>

        <div class="important-notice">
            <strong>IMPORTANT:</strong> This ticket is on hold pending payment. Please complete your payment within 24 hours to confirm your booking.
        </div>

        <h3>Passenger Details</h3>
        <table class="passenger-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Date of Birth</th>
                    <th>Passport</th>
                    <th>Nationality</th>
                </tr>
            </thead>
            <tbody>';

foreach($passengers as $passenger) {
    $html .= '
                <tr>
                    <td>' . htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']) . '</td>
                    <td>' . ucfirst($passenger['passenger_type']) . '</td>
                    <td>' . date('M d, Y', strtotime($passenger['date_of_birth'])) . '</td>
                    <td>' . htmlspecialchars($passenger['passport_number']) . '</td>
                    <td>' . htmlspecialchars($passenger['nationality']) . '</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>

        <div class="barcode">
            *' . $booking_ref . '*
        </div>

        <div class="footer">
            <p><strong>Terms & Conditions:</strong></p>
            <p>1. This e-ticket must be presented at check-in along with valid photo identification</p>
            <p>2. Check-in opens 3 hours before departure and closes 45 minutes before departure</p>
            <p>3. Baggage allowance: ' . htmlspecialchars($booking['baggage_allowance']) . '</p>
            <p>4. For assistance, contact airline customer service</p>
            <br>
            <p>Generated on ' . date('M d, Y H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the generated PDF
$dompdf->stream("ticket-$booking_ref.pdf", array("Attachment" => true));