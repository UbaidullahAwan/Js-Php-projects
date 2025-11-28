<?php
// includes/email-service.php - SIMPLIFIED VERSION
class EmailService {
    public function sendBookingConfirmation($toEmail, $passengerName, $pdfContent, $bookingReference) {
        // Simplified email sending - in production, integrate with your email service
        // For now, we'll just log the email and return true
        
        $subject = "Booking Confirmation - Reference: " . $bookingReference;
        $message = "
            Dear " . $passengerName . ",\n\n
            Your flight booking has been confirmed!\n\n
            Booking Reference: " . $bookingReference . "\n
            \n
            Thank you for choosing our service.\n\n
            Best regards,\n
            Hussain Group Team
        ";
        
        // Log the email instead of actually sending it
        $this->logEmail($toEmail, $subject, $message);
        
        // In a real application, you would use PHPMailer, SendGrid, or your email service here
        // For now, return true to simulate successful email sending
        return true;
    }
    
    private function logEmail($to, $subject, $message) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'message' => $message
        ];
        
        $logFile = __DIR__ . '/email_logs.txt';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
?>