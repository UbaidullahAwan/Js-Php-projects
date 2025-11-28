<?php
// pdf-templates/booking-confirmation.php
$booking = $bookingData;
$user = $userData;
$flight = $flightData;
$passenger = $passengerData;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Confirmation - <?php echo $booking['booking_reference']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #3498db; padding-bottom: 20px; }
        .logo { color: #3498db; font-size: 28px; font-weight: bold; margin-bottom: 10px; }
        .booking-ref { background: #3498db; color: white; padding: 10px; display: inline-block; border-radius: 5px; }
        .section { margin-bottom: 25px; }
        .section-title { background: #2c3e50; color: white; padding: 10px 15px; font-weight: bold; font-size: 16px; }
        .section-content { padding: 20px; border: 1px solid #ddd; border-top: none; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item { margin-bottom: 15px; }
        .label { font-weight: bold; color: #2c3e50; display: block; margin-bottom: 5px; }
        .value { color: #555; }
        .total { font-size: 18px; font-weight: bold; color: #27ae60; }
        .footer { margin-top: 40px; text-align: center; color: #7f8c8d; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
        .status-confirmed { color: #27ae60; font-weight: bold; }
        .agent-info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">SkyPass Travel</div>
        <h1>Flight Booking Confirmation</h1>
        <div class="booking-ref">Booking Reference: <?php echo $booking['booking_reference']; ?></div>
        <p>Status: <span class="status-confirmed">CONFIRMED</span></p>
    </div>

    <!-- Agent Information -->
    <div class="section">
        <div class="section-title">Booking Agent Information</div>
        <div class="section-content agent-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Agent Name:</span>
                    <span class="value"><?php echo htmlspecialchars($user['name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Agent Email:</span>
                    <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Agent ID:</span>
                    <span class="value"><?php echo htmlspecialchars($user['id']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Booking Date:</span>
                    <span class="value"><?php echo date('F j, Y \a\t H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Passenger Information -->
    <div class="section">
        <div class="section-title">Passenger Details</div>
        <div class="section-content">
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Full Name:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['phone']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Passport No:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['passport_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Date of Birth:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['date_of_birth']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Nationality:</span>
                    <span class="value"><?php echo htmlspecialchars($passenger['nationality']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flight Information -->
    <div class="section">
        <div class="section-title">Flight Details</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Class</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($flight['routes'] as $index => $route): ?>
                    <tr>
                        <td><?php echo $flight['airline_code'] . ' ' . $flight['flight_number']; ?></td>
                        <td><strong><?php echo $route['departure_city']; ?> → <?php echo $route['arrival_city']; ?></strong></td>
                        <td><?php echo date('M j, Y', strtotime($route['departure_time'])); ?></td>
                        <td><?php echo date('H:i', strtotime($route['departure_time'])) . ' - ' . date('H:i', strtotime($route['arrival_time'])); ?></td>
                        <td>
                            <?php
                            $departure = new DateTime($route['departure_time']);
                            $arrival = new DateTime($route['arrival_time']);
                            $interval = $departure->diff($arrival);
                            echo $interval->h . 'h ' . $interval->i . 'm';
                            ?>
                        </td>
                        <td><?php echo $flight['class']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Airline:</span>
                        <span class="value"><?php echo htmlspecialchars($flight['airline_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Baggage Allowance:</span>
                        <span class="value"><?php echo $flight['baggage_allowance']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Meal Service:</span>
                        <span class="value"><?php echo $flight['meal_available'] ? 'Included' : 'Not Included'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Flight Type:</span>
                        <span class="value"><?php echo ucwords(str_replace('_', ' ', $flight['flight_type'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Information -->
    <div class="section">
        <div class="section-title">Payment Summary</div>
        <div class="section-content">
            <table>
                <tr>
                    <td>Flight Fare</td>
                    <td>PKR <?php echo number_format($booking['flight_fare'], 2); ?></td>
                </tr>
                <tr>
                    <td>Taxes & Fees</td>
                    <td>PKR <?php echo number_format($booking['taxes'], 2); ?></td>
                </tr>
                <tr>
                    <td>Service Charge</td>
                    <td>PKR <?php echo number_format($booking['service_charge'], 2); ?></td>
                </tr>
                <tr style="border-top: 2px solid #2c3e50;">
                    <td class="total">Total Amount Paid</td>
                    <td class="total">PKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>Payment Method</td>
                    <td><?php echo htmlspecialchars($booking['payment_method']); ?></td>
                </tr>
                <tr>
                    <td>Payment Status</td>
                    <td class="status-confirmed"><?php echo $booking['payment_status']; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Important Notes -->
    <div class="section">
        <div class="section-title">Important Information</div>
        <div class="section-content">
            <ul style="line-height: 1.6;">
                <li>Please arrive at the airport at least 3 hours before departure for international flights</li>
                <li>Carry a printed copy of this confirmation and valid passport/ID</li>
                <li>Online check-in opens 48 hours before departure</li>
                <li>Baggage allowance: <?php echo $flight['baggage_allowance']; ?> per passenger</li>
                <li>For any changes or cancellations, contact your booking agent</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for choosing SkyPass Travel!</p>
        <p>For assistance, contact: <?php echo htmlspecialchars($user['email']); ?> | +92-XXX-XXXXXXX</p>
        <p>Generated automatically on: <?php echo date('F j, Y \a\t H:i:s'); ?></p>
    </div>
</body>
</html>