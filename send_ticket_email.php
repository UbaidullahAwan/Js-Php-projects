<?php
require_once 'config.php';

class EmailSystem {
    private $pdo;
    private $debug;
    
    public function __construct($pdo, $debug = false) {
        $this->pdo = $pdo;
        $this->debug = $debug;
    }
    
    /**
     * Send booking confirmation email with e-ticket attachment
     */
    public function sendBookingConfirmation($booking_ref) {
        try {
            // Get complete booking details
            $booking_data = $this->getBookingDetails($booking_ref);
            
            if (!$booking_data) {
                throw new Exception("Booking not found: " . $booking_ref);
            }
            
            if ($this->debug) {
                error_log("Booking data retrieved for: " . $booking_ref);
            }
            
            // Generate PDF ticket
            $pdf_content = $this->generateTicketPDF($booking_data);
            
            if ($this->debug) {
                error_log("PDF generated for: " . $booking_ref);
            }
            
            // Send email
            $email_sent = $this->sendEmail(
                $booking_data['user_email'],
                $booking_data['user_name'],
                $booking_ref,
                $booking_data,
                $pdf_content
            );
            
            // Log email sending attempt
            $this->logEmailActivity($booking_ref, $email_sent);
            
            return $email_sent;
            
        } catch (Exception $e) {
            error_log("Email sending failed for booking $booking_ref: " . $e->getMessage());
            $this->logEmailActivity($booking_ref, false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get complete booking details from database
     */
    private function getBookingDetails($booking_ref) {
        $sql = "SELECT 
                    b.booking_id, b.booking_reference, b.total_passengers, 
                    b.base_price, b.taxes_fees, b.total_amount,
                    b.booking_status, b.payment_status, b.created_at,
                    u.user_id, u.email as user_email, u.full_name as user_name,
                    f.flight_number, f.departure_city, f.arrival_city,
                    f.departure_date, f.departure_time, f.arrival_date, f.arrival_time,
                    f.duration, f.aircraft_type, f.class, f.baggage_allowance,
                    a.airline_name, a.airline_code
                FROM bookings b
                JOIN users u ON b.user_id = u.user_id
                JOIN flights f ON b.flight_id = f.flight_id
                JOIN airlines a ON f.airline_id = a.airline_id
                WHERE b.booking_reference = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$booking_ref]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return false;
        }
        
        // Get passenger details
        $passenger_sql = "SELECT * FROM passengers WHERE booking_id = ?";
        $passenger_stmt = $this->pdo->prepare($passenger_sql);
        $passenger_stmt->execute([$booking['booking_id']]);
        $booking['passengers'] = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $booking;
    }
    
    /**
     * Generate PDF ticket content
     */
    private function generateTicketPDF($booking_data) {
        // For now, let's create a simple text version to avoid dompdf dependency issues
        return $this->generateFallbackTicket($booking_data);
        
        /*
        // Uncomment this if you have dompdf installed
        // Start output buffering to capture PDF content
        ob_start();
        
        // Generate HTML for PDF
        $html = $this->generateTicketHTML($booking_data);
        
        // Use dompdf to generate PDF if available
        if (file_exists('vendor/autoload.php') && class_exists('Dompdf\Dompdf')) {
            require_once 'vendor/autoload.php';
            
            $options = new Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            $dompdf = new Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $pdf_content = $dompdf->output();
            ob_end_clean();
            return $pdf_content;
        } else {
            // Fallback: Create simple text PDF or HTML email
            $pdf_content = $this->generateFallbackTicket($booking_data);
            ob_end_clean();
            return $pdf_content;
        }
        */
    }
    
    /**
     * Generate HTML content for PDF ticket
     */
    private function generateTicketHTML($booking_data) {
        $booking = $booking_data;
        $passengers = $booking['passengers'];
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { text-align: center; margin-bottom: 20px; }
                .booking-info { margin: 20px 0; padding: 15px; background: #f5f5f5; }
                .flight-route { display: flex; justify-content: space-between; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ELECTRONIC TICKET RECEIPT</h1>
                <div style="background: #ffc107; padding: 10px; display: inline-block;">
                    TICKET ON HOLD - PAYMENT PENDING
                </div>
            </div>
            
            <div class="booking-info">
                <strong>Booking Reference:</strong> ' . $booking['booking_reference'] . '<br>
                <strong>Flight:</strong> ' . htmlspecialchars($booking['flight_number']) . '<br>
                <strong>Route:</strong> ' . htmlspecialchars($booking['departure_city']) . ' to ' . htmlspecialchars($booking['arrival_city']) . '
            </div>
            
            <h2>Passenger Details</h2>
            <table border="1" cellpadding="8" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th>Passenger Name</th>
                    <th>Type</th>
                    <th>Passport No.</th>
                </tr>';
        
        foreach($passengers as $passenger) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']) . '</td>
                    <td>' . ucfirst($passenger['passenger_type']) . '</td>
                    <td>' . htmlspecialchars($passenger['passport_number']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd;">
                <strong>IMPORTANT:</strong> Complete payment within 24 hours to confirm booking.
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Fallback ticket generation if dompdf is not available
     */
    private function generateFallbackTicket($booking_data) {
        $booking = $booking_data;
        $text = "ELECTRONIC TICKET RECEIPT\n";
        $text .= "========================\n\n";
        $text .= "Airline: " . $booking['airline_name'] . "\n";
        $text .= "Booking Reference: " . $booking['booking_reference'] . "\n";
        $text .= "Status: TICKET ON HOLD - PAYMENT PENDING\n\n";
        $text .= "Flight: " . $booking['flight_number'] . "\n";
        $text .= "Route: " . $booking['departure_city'] . " to " . $booking['arrival_city'] . "\n";
        $text .= "Date: " . date('F d, Y', strtotime($booking['departure_date'])) . "\n";
        $text .= "Time: " . date('H:i', strtotime($booking['departure_time'])) . "\n\n";
        $text .= "Passengers:\n";
        
        foreach($booking['passengers'] as $passenger) {
            $text .= "- " . $passenger['first_name'] . " " . $passenger['last_name'] . 
                    " (" . $passenger['passenger_type'] . ")\n";
        }
        
        $text .= "\nIMPORTANT: Complete payment within 24 hours to confirm booking.\n";
        return $text;
    }
    
    /**
     * Send email with PDF attachment - MULTIPLE METHODS
     */
    private function sendEmail($to, $user_name, $booking_ref, $booking_data, $pdf_content) {
        $subject = "Your Flight E-Ticket - Booking Reference: " . $booking_ref;
        
        // HTML email content
        $message = $this->generateEmailHTML($user_name, $booking_ref, $booking_data);
        
        // Try multiple email sending methods
        $methods = ['phpmailer', 'native_with_headers', 'simple_text'];
        
        foreach ($methods as $method) {
            $result = $this->sendEmailWithMethod($method, $to, $subject, $message, $booking_ref, $pdf_content);
            if ($result) {
                if ($this->debug) {
                    error_log("Email sent successfully using method: $method to: $to");
                }
                return true;
            }
        }
        
        if ($this->debug) {
            error_log("All email sending methods failed for: $to");
        }
        return false;
    }
    
    /**
     * Try different email sending methods
     */
    private function sendEmailWithMethod($method, $to, $subject, $message, $booking_ref, $pdf_content) {
        switch ($method) {
            case 'phpmailer':
                return $this->sendWithPHPMailer($to, $subject, $message, $booking_ref, $pdf_content);
                
            case 'native_with_headers':
                return $this->sendWithNativeMail($to, $subject, $message, $booking_ref, $pdf_content);
                
            case 'simple_text':
                return $this->sendSimpleTextEmail($to, $subject, $booking_ref, $booking_data);
                
            default:
                return false;
        }
    }
    
    /**
     * Method 1: Using PHPMailer (Recommended)
     */
    private function sendWithPHPMailer($to, $subject, $message, $booking_ref, $pdf_content) {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                if (file_exists('vendor/autoload.php')) {
                    require_once 'vendor/autoload.php';
                } else {
                    return false; // PHPMailer not available
                }
            }
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Change to your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com'; // Change this
            $mail->Password = 'your-app-password'; // Change this
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('noreply@airline.com', 'Airline Booking System');
            $mail->addAddress($to);
            $mail->addReplyTo('support@airline.com', 'Customer Service');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $this->generateTextEmail($booking_ref, $booking_data);
            
            // Attachment
            $mail->addStringAttachment($pdf_content, "ticket_$booking_ref.pdf", 'base64', 'application/pdf');
            
            return $mail->send();
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("PHPMailer failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Method 2: Using native mail() function with proper headers
     */
    private function sendWithNativeMail($to, $subject, $message, $booking_ref, $pdf_content) {
        try {
            $from = "noreply@" . $this->getDomain();
            $reply_to = "support@" . $this->getDomain();
            
            // Headers
            $headers = "From: Airline Booking System <$from>\r\n";
            $headers .= "Reply-To: $reply_to\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            
            // Create boundary
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            // Email body
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $message . "\r\n\r\n";
            
            // PDF attachment
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/pdf; name=\"ticket_$booking_ref.pdf\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"ticket_$booking_ref.pdf\"\r\n\r\n";
            $body .= chunk_split(base64_encode($pdf_content)) . "\r\n\r\n";
            $body .= "--$boundary--";
            
            // Send email
            $result = mail($to, $subject, $body, $headers, "-f $from");
            
            if ($this->debug) {
                error_log("Native mail result: " . ($result ? 'true' : 'false'));
                error_log("To: $to, Subject: $subject");
            }
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Native mail failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Method 3: Simple text email without attachment
     */
    private function sendSimpleTextEmail($to, $subject, $booking_ref, $booking_data) {
        try {
            $text_body = $this->generateTextEmail($booking_ref, $booking_data);
            
            $headers = "From: noreply@" . $this->getDomain() . "\r\n";
            $headers .= "Reply-To: support@" . $this->getDomain() . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            return mail($to, $subject, $text_body, $headers);
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Simple text email failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Generate text-only email content
     */
    private function generateTextEmail($booking_ref, $booking_data) {
        $flight = $booking_data;
        return "
FLIGHT BOOKING CONFIRMATION
============================

Booking Reference: $booking_ref

Hello Valued Customer,

Your flight booking has been successfully processed.

IMPORTANT: Your ticket is currently on hold pending payment confirmation.
Please complete your payment within 24 hours to secure your booking.

Flight Details:
- Airline: {$flight['airline_name']}
- Flight: {$flight['flight_number']}
- Route: {$flight['departure_city']} to {$flight['arrival_city']}
- Date: " . date('F d, Y', strtotime($flight['departure_date'])) . "
- Time: " . date('H:i', strtotime($flight['departure_time'])) . "

Passengers: " . count($flight['passengers']) . "

Complete your payment at: " . $this->getBaseUrl() . "payment.php?booking_ref=$booking_ref

Thank you for choosing our airline service!

Customer Service: support@airline.com
";
    }
    
    /**
     * Generate HTML email content
     */
    private function generateEmailHTML($user_name, $booking_ref, $booking_data) {
        $flight = $booking_data;
        $passenger_count = count($booking_data['passengers']);
        
        $base_url = $this->getBaseUrl();
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { background: white; padding: 20px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✈️ Flight Booking Confirmation</h1>
                    <p>Booking Reference: <strong>$booking_ref</strong></p>
                </div>
                
                <div class='content'>
                    <h2>Hello " . ($user_name ?: 'Valued Customer') . ",</h2>
                    <p>Your flight booking has been successfully processed.</p>
                    
                    <div style='background: #fff3cd; padding: 15px; margin: 15px 0;'>
                        <h3>⚠️ Important Payment Notice</h3>
                        <p><strong>Your ticket is currently on hold pending payment confirmation.</strong></p>
                        <p>Please complete your payment within <strong>24 hours</strong> to secure your booking.</p>
                    </div>
                    
                    <h3>Flight Details</h3>
                    <p><strong>Airline:</strong> " . htmlspecialchars($flight['airline_name']) . "</p>
                    <p><strong>Flight:</strong> " . htmlspecialchars($flight['flight_number']) . "</p>
                    <p><strong>Route:</strong> " . htmlspecialchars($flight['departure_city']) . " to " . htmlspecialchars($flight['arrival_city']) . "</p>
                    <p><strong>Date:</strong> " . date('F d, Y', strtotime($flight['departure_date'])) . "</p>
                    <p><strong>Time:</strong> " . date('H:i', strtotime($flight['departure_time'])) . "</p>
                    <p><strong>Passengers:</strong> $passenger_count</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='{$base_url}payment.php?booking_ref=$booking_ref' 
                           style='background: #27ae60; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                            💳 Complete Payment
                        </a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Thank you for choosing our airline service!</p>
                    <p>Customer Service: support@airline.com</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get base URL for links in emails
     */
    private function getBaseUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_path = dirname($_SERVER['PHP_SELF'] ?? '');
        return $protocol . $host . $script_path . '/';
    }
    
    /**
     * Get domain for email addresses
     */
    private function getDomain() {
        return $_SERVER['HTTP_HOST'] ?? 'airline.com';
    }
    
    /**
     * Log email activity for tracking
     */
    private function logEmailActivity($booking_ref, $success, $error_message = null) {
        $log_message = date('Y-m-d H:i:s') . " - Booking: $booking_ref - ";
        $log_message .= $success ? "Email sent successfully" : "Email failed: " . ($error_message ?? 'Unknown error');
        
        // Log to file
        error_log($log_message . "\n", 3, "email_logs.txt");
        
        // Log to database if table exists
        try {
            $log_sql = "INSERT INTO email_logs (booking_reference, sent_status, error_message, sent_at) 
                       VALUES (?, ?, ?, NOW())";
            $log_stmt = $this->pdo->prepare($log_sql);
            $log_stmt->execute([$booking_ref, $success ? 1 : 0, $error_message]);
        } catch (Exception $e) {
            // Silently fail if logging to database fails
        }
    }
}

// Helper function to quickly send booking confirmation
function sendBookingConfirmationEmail($booking_ref, $debug = false) {
    global $pdo;
    $emailSystem = new EmailSystem($pdo, $debug);
    return $emailSystem->sendBookingConfirmation($booking_ref);
}

// Create email logs table if it doesn't exist
function createEmailLogsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_reference VARCHAR(20),
        sent_status TINYINT(1),
        error_message TEXT,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking_ref (booking_reference),
        INDEX idx_sent_at (sent_at)
    )";
    
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Failed to create email_logs table: " . $e->getMessage());
    }
}

// Initialize email logs table if pdo exists
if (isset($pdo)) {
    createEmailLogsTable($pdo);
}
?>