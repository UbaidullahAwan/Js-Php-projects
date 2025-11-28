<?php
// process_booking.php
session_start();
include 'config.php';

// DOMPDF SETUP - AT THE TOP OF THE FILE
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

if(!isset($_SESSION['user_logged_in']) || !isset($_SESSION['booking_data'])) {
    header('Location: flights.php'); // Changed from booking_success.php
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_data = $_SESSION['booking_data'];
$flight = $booking_data['flight_data'];
$passenger_data = $booking_data['passenger_data'];

try {
    $pdo->beginTransaction();

    // Generate booking reference
    $booking_reference = 'SKP' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // 1. Create booking record
    $booking_sql = "INSERT INTO bookings (user_id, booking_date, total_amount, booking_status, payment_status, trip_type, booking_reference) 
                    VALUES (?, NOW(), ?, 'on_hold', 'pending', ?, ?)";
    $booking_stmt = $pdo->prepare($booking_sql);
    $booking_stmt->execute([
        $user_id, 
        $booking_data['total_amount'], 
        $flight['flight_type'],
        $booking_reference
    ]);
    $booking_id = $pdo->lastInsertId();

    // 2. Create booking segments
    foreach($booking_data['route_info'] as $route) {
        $segment_sql = "INSERT INTO booking_segments (booking_id, route_id, segment_order, travel_date, segment_price, passenger_count) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $segment_stmt = $pdo->prepare($segment_sql);
        $segment_stmt->execute([
            $booking_id,
            $route['route_id'],
            $route['segment_order'],
            date('Y-m-d', strtotime($route['departure_time'])),
            $flight['price'],
            $booking_data['num_adults']
        ]);
    }

    // 3. Create passenger records
    foreach($passenger_data as $index => $passenger) {
        $passenger_sql = "INSERT INTO passengers (booking_id, full_name, date_of_birth, gender, passport_number, passport_expiry, nationality, seat_number) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $passenger_stmt = $pdo->prepare($passenger_sql);
        $passenger_stmt->execute([
            $booking_id,
            $passenger['title'] . ' ' . $passenger['full_name'],
            $passenger['date_of_birth'],
            $passenger['gender'],
            $passenger['passport_number'],
            $passenger['passport_expiry'],
            $passenger['nationality'],
            'TBD' // Seat will be assigned after payment
        ]);
    }

    // 4. Update available seats
    $update_seats_sql = "UPDATE flights SET available_seats = available_seats - ? WHERE flight_id = ?";
    $update_stmt = $pdo->prepare($update_seats_sql);
    $update_stmt->execute([$booking_data['num_adults'], $flight['flight_id']]);

    // 5. Create payment record
    $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id, payment_status) 
                    VALUES (?, ?, NOW(), 'pending', NULL, 'pending')";
    $payment_stmt = $pdo->prepare($payment_sql);
    $payment_stmt->execute([$booking_id, $booking_data['total_amount']]);

    $pdo->commit();

    // Generate PDF with ON HOLD status
    generateBookingPDF($booking_id, $booking_reference, $user_id, $booking_data, 'on_hold');

    // Store booking ID in session
    $_SESSION['booking_id'] = $booking_id;
    $_SESSION['booking_reference'] = $booking_reference;
    $_SESSION['booking_complete'] = true;

    // Send confirmation email with PDF attachment
    sendBookingEmail($user_id, $booking_id, $booking_reference, $booking_data);

    header('Location: booking_success.php'); // Changed from booking_confirmation.php
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Booking failed: " . $e->getMessage());
}

function generateBookingPDF($booking_id, $booking_reference, $user_id, $booking_data, $status = 'on_hold') {
    global $pdo;
    
    // Get user details
    $user_sql = "SELECT * FROM users WHERE Id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Get passenger details (first passenger)
    $passenger_sql = "SELECT * FROM passengers WHERE booking_id = ? LIMIT 1";
    $passenger_stmt = $pdo->prepare($passenger_sql);
    $passenger_stmt->execute([$booking_id]);
    $passenger = $passenger_stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare data for PDF template
    $pdfData = [
        'booking' => [
            'booking_reference' => $booking_reference,
            'total_amount' => $booking_data['total_amount'],
            'flight_fare' => $booking_data['total_amount'] * 0.8, // 80% base fare
            'taxes' => $booking_data['total_amount'] * 0.15, // 15% taxes
            'service_charge' => $booking_data['total_amount'] * 0.05, // 5% service charge
            'payment_method' => 'Pending',
            'payment_status' => strtoupper($status)
        ],
        'user' => [
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'id' => $user['Id']
        ],
        'passenger' => [
            'full_name' => $passenger['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone_number'],
            'passport_number' => $passenger['passport_number'],
            'date_of_birth' => $passenger['date_of_birth'],
            'nationality' => $passenger['nationality']
        ],
        'flight' => [
            'airline_name' => $booking_data['flight_data']['airline_name'],
            'airline_code' => $booking_data['flight_data']['airline_code'],
            'flight_number' => $booking_data['flight_data']['flight_number'],
            'flight_type' => $booking_data['flight_data']['flight_type'],
            'baggage_allowance' => $booking_data['flight_data']['baggage_allowance'],
            'meal_available' => $booking_data['flight_data']['meal_included'] == 'yes',
            'class' => 'Economy',
            'routes' => $booking_data['route_info']
        ]
    ];

    // Generate PDF using your template
    ob_start();
    
    // Set variables for the PDF template
    $booking = $pdfData['booking'];
    $user = $pdfData['user'];
    $passenger = $pdfData['passenger'];
    $flight = $pdfData['flight'];
    
    // Use absolute path for include to avoid path issues
    $pdf_template_path = __DIR__ . '/pdf-templates/booking-confirmation.php';
    if (!file_exists($pdf_template_path)) {
        throw new Exception("PDF template not found: " . $pdf_template_path);
    }

    include $pdf_template_path;
    $html_content = ob_get_clean();

    // Validate that we got HTML content
    if (empty($html_content) || strlen($html_content) < 100) {
        throw new Exception("Failed to generate HTML content from template");
    }

    try {
        // Create PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // Add default font for better compatibility
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Create pdfs directory if it doesn't exist
        $pdfs_dir = 'pdfs';
        if (!is_dir($pdfs_dir)) {
            if (!mkdir($pdfs_dir, 0755, true)) {
                throw new Exception("Failed to create PDF directory: " . $pdfs_dir);
            }
        }
        
        // Save PDF file
        $pdf_output = $dompdf->output();
        if (empty($pdf_output)) {
            throw new Exception("PDF generation failed - empty output");
        }
        
        $pdf_filename = "booking_{$booking_reference}_{$status}.pdf";
        $pdf_full_path = $pdfs_dir . '/' . $pdf_filename;
        
        if (file_put_contents($pdf_full_path, $pdf_output) === false) {
            throw new Exception("Failed to save PDF file: " . $pdf_full_path);
        }
        
        // Verify the file was created
        if (!file_exists($pdf_full_path) || filesize($pdf_full_path) === 0) {
            throw new Exception("PDF file was not created or is empty: " . $pdf_full_path);
        }
        
        return $pdf_filename;
        
    } catch (Exception $e) {
        throw new Exception("PDF generation failed: " . $e->getMessage());
    }
}

function sendBookingEmail($user_id, $booking_id, $booking_reference, $booking_data) {
    global $pdo;
    
    // Get user details
    $user_sql = "SELECT * FROM users WHERE Id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $to = $user['email'];
    $subject = "Booking On Hold - {$booking_reference}";
    
    $pdf_path = "pdfs/booking_{$booking_reference}_on_hold.pdf";
    
    // Email headers
    $headers = "From: bookings@yourwebsite.com\r\n";
    $headers .= "Reply-To: bookings@yourwebsite.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Boundary for mixed content
    $boundary = md5(time());
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    // Email message
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background: #f39c12; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; }
            .status-onhold { color: #f39c12; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Booking On Hold</h1>
            <p>Booking Reference: <strong>{$booking_reference}</strong></p>
        </div>
        <div class='content'>
            <h2>Dear " . $user['first_name'] . " " . $user['last_name'] . ",</h2>
            <p>Your flight booking has been received and is currently <span class='status-onhold'>ON HOLD</span>.</p>
            
            <div class='warning'>
                <h3>⚠️ Important Payment Notice</h3>
                <p>You have <strong>3 hours</strong> to complete your payment. If payment is not received within this time, 
                your booking will be automatically cancelled and the seats will be released.</p>
                <p><strong>Payment Deadline: " . date('Y-m-d H:i:s', strtotime('+3 hours')) . "</strong></p>
            </div>

            <h3>Booking Summary:</h3>
            <p><strong>Airline:</strong> " . $booking_data['flight_data']['airline_name'] . "</p>
            <p><strong>Flight Number:</strong> " . $booking_data['flight_data']['flight_number'] . "</p>
            <p><strong>Total Amount:</strong> PKR " . number_format($booking_data['total_amount'], 0) . "</p>
            <p><strong>Passengers:</strong> " . $booking_data['num_adults'] . " adult(s)</p>

            <p>Please find your booking confirmation attached. This is a temporary hold confirmation.</p>
            <p><a href='http://yourwebsite.com/payment.php?booking_id=" . $booking_id . "' style='background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;'>Complete Payment Now</a></p>
        </div>
        <div class='footer'>
            <p>Thank you for choosing our service!</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </body>
    </html>
    \r\n\r\n";

    // Add PDF attachment
    if(file_exists($pdf_path)) {
        $file_content = file_get_contents($pdf_path);
        $file_encoded = chunk_split(base64_encode($file_content));
        
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/pdf; name=\"Booking_{$booking_reference}.pdf\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"Booking_{$booking_reference}.pdf\"\r\n\r\n";
        $message .= $file_encoded . "\r\n";
    }
    
    $message .= "--$boundary--";

    // Send email
    if(mail($to, $subject, $message, $headers)) {
        // Log email sent
        $log_sql = "INSERT INTO email_logs (booking_reference, sent_status, error_message) VALUES (?, 1, NULL)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$booking_reference]);
    } else {
        // Log email failure
        $log_sql = "INSERT INTO email_logs (booking_reference, sent_status, error_message) VALUES (?, 0, 'Failed to send email')";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$booking_reference]);
    }
}
?>