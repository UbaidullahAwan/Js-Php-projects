<?php
// includes/pdf-generator.php - SIMPLIFIED VERSION
class PDFGenerator {
    public function generateBookingConfirmation($bookingData, $userData, $flight, $passengerData) {
        // Generate a simple HTML confirmation instead of PDF
        // In production, you can use a proper PDF library like TCPDF or Dompdf
        
        $htmlContent = $this->generateHTMLConfirmation($bookingData, $userData, $flight, $passengerData);
        
        // Save HTML content to file (you can modify this to actually generate PDF)
        $filename = 'booking_' . $bookingData['booking_reference'] . '.html';
        $filepath = __DIR__ . '/../bookings/' . $filename;
        
        // Ensure bookings directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, $htmlContent);
        
        return $htmlContent; // Return HTML content instead of PDF
    }
    
    private function generateHTMLConfirmation($bookingData, $userData, $flight, $passengerData) {
        $isMultiPassenger = is_array($passengerData[0] ?? null);
        $passengers = $isMultiPassenger ? $passengerData : [$passengerData];
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Booking Confirmation - " . $bookingData['booking_reference'] . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { background: #3498db; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                .booking-ref { font-size: 24px; font-weight: bold; }
                .passenger-table { width: 100%; border-collapse: collapse; }
                .passenger-table th, .passenger-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .passenger-table th { background: #f8f9fa; }
                .total { font-size: 18px; font-weight: bold; color: #2c3e50; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Flight Booking Confirmation</h1>
                <div class='booking-ref'>Booking Reference: " . $bookingData['booking_reference'] . "</div>
            </div>
            
            <div class='section'>
                <h2>Flight Details</h2>
                <p><strong>Airline:</strong> " . htmlspecialchars($flight['airline_name']) . "</p>
                <p><strong>Flight Number:</strong> " . htmlspecialchars($flight['flight_number']) . "</p>
                <p><strong>Flight Type:</strong> " . ucwords(str_replace('_', ' ', $flight['flight_type'])) . "</p>
            </div>
            
            <div class='section'>
                <h2>Passenger Information</h2>
                <table class='passenger-table'>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Passport</th>
                            <th>Date of Birth</th>
                            <th>Nationality</th>
                        </tr>
                    </thead>
                    <tbody>";
        
        foreach ($passengers as $passenger) {
            $html .= "
                        <tr>
                            <td>" . htmlspecialchars($passenger['full_name']) . "</td>
                            <td>" . htmlspecialchars($passenger['email']) . "</td>
                            <td>" . htmlspecialchars($passenger['phone']) . "</td>
                            <td>" . htmlspecialchars($passenger['passport_number']) . "</td>
                            <td>" . htmlspecialchars($passenger['date_of_birth']) . "</td>
                            <td>" . htmlspecialchars($passenger['nationality']) . "</td>
                        </tr>";
        }
        
        $html .= "
                    </tbody>
                </table>
            </div>
            
            <div class='section'>
                <h2>Booking Summary</h2>
                <p><strong>Number of Adults:</strong> " . $bookingData['num_adults'] . "</p>
                <p><strong>Price per Adult:</strong> PKR " . number_format($bookingData['flight_fare'], 0) . "</p>
                <p class='total'>Total Amount: PKR " . number_format($bookingData['total_amount'], 0) . "</p>
                <p><strong>Booking Agent:</strong> " . htmlspecialchars($userData['name']) . "</p>
                <p><strong>Booking Date:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <div class='section'>
                <h2>Contact Information</h2>
                <p>If you have any questions, please contact our support team.</p>
                <p><strong>Hussain Group</strong><br>
                Email: support@hussaingroup.com<br>
                Phone: +92-XXX-XXXXXXX</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
}
?>