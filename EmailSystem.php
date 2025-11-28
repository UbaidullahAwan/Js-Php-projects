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
    public function sendBookingConfirmation($booking_id) {
        try {
            // Get complete booking details
            $booking_data = $this->getBookingDetails($booking_id);
            
            if (!$booking_data) {
                throw new Exception("Booking not found: " . $booking_id);
            }
            
            if ($this->debug) {
                error_log("Booking data retrieved for: " . $booking_id);
            }
            
            // Generate PDF ticket
            $pdf_content = $this->generateTicketPDF($booking_data);
            
            if ($this->debug) {
                error_log("PDF generated for: " . $booking_id);
            }
            
            // Send email
            $email_sent = $this->sendEmail(
                $booking_data['user_email'],
                $booking_data['user_name'],
                $booking_id,
                $booking_data,
                $pdf_content
            );
            
            // Log email sending attempt
            $this->logEmailActivity($booking_id, $email_sent);
            
            return $email_sent;
            
        } catch (Exception $e) {
            error_log("Email sending failed for booking $booking_id: " . $e->getMessage());
            $this->logEmailActivity($booking_id, false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get complete booking details from database - UPDATED FOR YOUR SCHEMA
     */
    private function getBookingDetails($booking_id) {
        $sql = "SELECT 
                    b.booking_id, 
                    b.total_amount,
                    b.booking_status, 
                    b.payment_status, 
                    b.booking_date as created_at,
                    u.Id as user_id, 
                    u.email as user_email, 
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    f.flight_id,
                    f.flight_number, 
                    f.baggage_allowance,
                    f.meal_included,
                    a.airline_name, 
                    a.airline_code,
                    fr.departure_city,
                    fr.arrival_city,
                    fr.departure_time,
                    fr.arrival_time,
                    fr.flight_duration
                FROM bookings b
                JOIN users u ON b.user_id = u.Id
                JOIN booking_segments bs ON b.booking_id = bs.booking_id
                JOIN flight_routes fr ON bs.route_id = fr.route_id
                JOIN flights f ON fr.flight_id = f.flight_id
                JOIN airlines a ON f.airline_id = a.airline_id
                WHERE b.booking_id = ? 
                ORDER BY bs.segment_order ASC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            // Alternative query for multi_city_bookings
            $sql = "SELECT 
                        b.booking_id, 
                        b.total_amount,
                        b.booking_status, 
                        b.booking_date as created_at,
                        u.Id as user_id, 
                        u.email as user_email, 
                        CONCAT(u.first_name, ' ', u.last_name) as user_name,
                        'confirmed' as payment_status
                    FROM multi_city_bookings b
                    JOIN users u ON b.user_id = u.Id
                    WHERE b.booking_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return false;
            }
        }
        
        // Get passenger details
        $passenger_sql = "SELECT * FROM passengers WHERE booking_id = ?";
        $passenger_stmt = $this->pdo->prepare($passenger_sql);
        $passenger_stmt->execute([$booking_id]);
        $booking['passengers'] = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no passengers found, create a dummy passenger for testing
        if (empty($booking['passengers'])) {
            $booking['passengers'] = [
                [
                    'first_name' => 'Test',
                    'last_name' => 'Passenger',
                    'passenger_type' => 'adult',
                    'date_of_birth' => '1990-01-01',
                    'passport_number' => 'AB123456',
                    'nationality' => 'Pakistani'
                ]
            ];
        }
        
        return $booking;
    }
    
    /**
     * Generate PDF ticket content
     */
    private function generateTicketPDF($booking_data) {
        // For now, create a simple text version
        return $this->generateFallbackTicket($booking_data);
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
                    BOOKING CONFIRMED
                </div>
            </div>
            
            <div class="booking-info">
                <strong>Booking ID:</strong> ' . $booking['booking_id'] . '<br>
                <strong>Flight:</strong> ' . htmlspecialchars($booking['flight_number'] ?? 'N/A') . '<br>
                <strong>Route:</strong> ' . htmlspecialchars($booking['departure_city'] ?? 'N/A') . ' to ' . htmlspecialchars($booking['arrival_city'] ?? 'N/A') . '<br>
                <strong>Total Amount:</strong> PKR ' . number_format($booking['total_amount'], 2) . '
            </div>
            
            <h2>Passenger Details</h2>
            <table border="1" cellpadding="8" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th>Passenger Name</th>
                    <th>Passport No.</th>
                    <th>Nationality</th>
                </tr>';
        
        foreach($passengers as $passenger) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']) . '</td>
                    <td>' . htmlspecialchars($passenger['passport_number']) . '</td>
                    <td>' . htmlspecialchars($passenger['nationality']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #e3f2fd;">
                <strong>Thank you for your booking!</strong><br>
                Please present this e-ticket at the airport check-in counter.
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Fallback ticket generation
     */
    private function generateFallbackTicket($booking_data) {
        $booking = $booking_data;
        $text = "ELECTRONIC TICKET RECEIPT\n";
        $text .= "========================\n\n";
        $text .= "Airline: " . ($booking['airline_name'] ?? 'N/A') . "\n";
        $text .= "Booking ID: " . $booking['booking_id'] . "\n";
        $text .= "Status: BOOKING CONFIRMED\n\n";
        $text .= "Flight: " . ($booking['flight_number'] ?? 'N/A') . "\n";
        $text .= "Route: " . ($booking['departure_city'] ?? 'N/A') . " to " . ($booking['arrival_city'] ?? 'N/A') . "\n";
        $text .= "Total Amount: PKR " . number_format($booking['total_amount'], 2) . "\n\n";
        $text .= "Passengers:\n";
        
        foreach($booking['passengers'] as $passenger) {
            $text .= "- " . $passenger['first_name'] . " " . $passenger['last_name'] . 
                    " (Passport: " . $passenger['passport_number'] . ")\n";
        }
        
        $text .= "\nThank you for your booking!\n";
        return $text;
    }
    
    /**
     * Send email with PDF attachment
     */
    private function sendEmail($to, $user_name, $booking_id, $booking_data, $pdf_content) {
        $subject = "Your Flight E-Ticket - Booking ID: " . $booking_id;
        
        // HTML email content
        $message = $this->generateEmailHTML($user_name, $booking_id, $booking_data);
        
        // Try multiple email sending methods
        $methods = ['native_with_headers', 'simple_text'];
        
        foreach ($methods as $method) {
            $result = $this->sendEmailWithMethod($method, $to, $subject, $message, $booking_id, $pdf_content);
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
    private function sendEmailWithMethod($method, $to, $subject, $message, $booking_id, $pdf_content) {
        switch ($method) {
            case 'native_with_headers':
                return $this->sendWithNativeMail($to, $subject, $message, $booking_id, $pdf_content);
                
            case 'simple_text':
                return $this->sendSimpleTextEmail($to, $subject, $booking_id, $booking_data);
                
            default:
                return false;
        }
    }
    
    /**
     * Method 1: Using native mail() function with proper headers
     */
    private function sendWithNativeMail($to, $subject, $message, $booking_id, $pdf_content) {
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
            $body .= "Content-Type: application/pdf; name=\"ticket_$booking_id.pdf\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"ticket_$booking_id.pdf\"\r\n\r\n";
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
     * Method 2: Simple text email without attachment
     */
    private function sendSimpleTextEmail($to, $subject, $booking_id, $booking_data) {
        try {
            $text_body = $this->generateTextEmail($booking_id, $booking_data);
            
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
    private function generateTextEmail($booking_id, $booking_data) {
        $flight = $booking_data;
        return "
FLIGHT BOOKING CONFIRMATION
============================

Booking ID: $booking_id

Hello Valued Customer,

Your flight booking has been successfully processed.

Flight Details:
- Airline: " . ($flight['airline_name'] ?? 'N/A') . "
- Flight: " . ($flight['flight_number'] ?? 'N/A') . "
- Route: " . ($flight['departure_city'] ?? 'N/A') . " to " . ($flight['arrival_city'] ?? 'N/A') . "
- Total Amount: PKR " . number_format($flight['total_amount'], 2) . "

Passengers: " . count($flight['passengers']) . "

Thank you for choosing our airline service!

Customer Service: support@airline.com
";
    }
    
    /**
     * Generate HTML email content
     */
    private function generateEmailHTML($user_name, $booking_id, $booking_data) {
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
                    <p>Booking ID: <strong>$booking_id</strong></p>
                </div>
                
                <div class='content'>
                    <h2>Hello " . ($user_name ?: 'Valued Customer') . ",</h2>
                    <p>Your flight booking has been successfully processed. We've attached your e-ticket to this email for your convenience.</p>
                    
                    <h3>Flight Details</h3>
                    <p><strong>Airline:</strong> " . htmlspecialchars($flight['airline_name'] ?? 'N/A') . "</p>
                    <p><strong>Flight:</strong> " . htmlspecialchars($flight['flight_number'] ?? 'N/A') . "</p>
                    <p><strong>Route:</strong> " . htmlspecialchars($flight['departure_city'] ?? 'N/A') . " to " . htmlspecialchars($flight['arrival_city'] ?? 'N/A') . "</p>
                    <p><strong>Total Amount:</strong> PKR " . number_format($flight['total_amount'], 2) . "</p>
                    <p><strong>Passengers:</strong> $passenger_count</p>
                    
                    <div style='background: #e3f2fd; padding: 15px; margin: 15px 0;'>
                        <h3>📋 Next Steps</h3>
                        <ol>
                            <li><strong>Check-in:</strong> Online check-in opens 24 hours before departure</li>
                            <li><strong>Airport:</strong> Arrive at least 2 hours before departure</li>
                            <li><strong>Documents:</strong> Bring your e-ticket and valid ID/passport</li>
                        </ol>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Thank you for choosing our airline service!</p>
                    <p>Customer Service: support@airline.com | Phone: +1-800-FLY-AWAY</p>
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
    private function logEmailActivity($booking_id, $success, $error_message = null) {
        $log_message = date('Y-m-d H:i:s') . " - Booking: $booking_id - ";
        $log_message .= $success ? "Email sent successfully" : "Email failed: " . ($error_message ?? 'Unknown error');
        
        // Log to file
        error_log($log_message . "\n", 3, "email_logs.txt");
        
        // Log to database
        try {
            $log_sql = "INSERT INTO email_logs (booking_reference, sent_status, error_message, sent_at) 
                       VALUES (?, ?, ?, NOW())";
            $log_stmt = $this->pdo->prepare($log_sql);
            $log_stmt->execute([$booking_id, $success ? 1 : 0, $error_message]);
        } catch (Exception $e) {
            // Silently fail if logging to database fails
        }
    }
}

// Helper function to quickly send booking confirmation
function sendBookingConfirmationEmail($booking_id, $debug = false) {
    global $pdo;
    $emailSystem = new EmailSystem($pdo, $debug);
    return $emailSystem->sendBookingConfirmation($booking_id);
}
?>