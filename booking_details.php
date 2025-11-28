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

// Fetch booking details
try {
    // Booking and flight details
    $booking_sql = "SELECT b.*, f.*, a.airline_name, a.airline_code 
                   FROM bookings b 
                   JOIN flights f ON b.flight_id = f.flight_id 
                   JOIN airlines a ON f.airline_id = a.airline_id 
                   WHERE b.booking_reference = ? AND b.user_id = ?";
    
    $booking_stmt = $pdo->prepare($booking_sql);
    $booking_stmt->execute([$booking_ref, $_SESSION['user_id']]);
    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$booking) {
        die('Booking not found or access denied.');
    }
    
    // Passenger details
    $passenger_sql = "SELECT * FROM passengers WHERE booking_id = ?";
    $passenger_stmt = $pdo->prepare($passenger_sql);
    $passenger_stmt->execute([$booking['booking_id']]);
    $passengers = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - <?php echo $booking_ref; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add the same styles from booking_review.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .booking-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .flight-details-card, .price-summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .price-summary-card {
            border-left-color: #27ae60;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .flight-route {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .route-city {
            text-align: center;
        }

        .city-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .city-details {
            font-size: 14px;
            color: #666;
        }

        .route-arrow {
            color: #667eea;
            font-size: 24px;
        }

        .flight-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .info-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }

        .price-breakdown {
            margin-bottom: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .price-item:last-child {
            border-bottom: none;
        }

        .price-label {
            color: #666;
        }

        .price-amount {
            font-weight: 600;
            color: #333;
        }

        .total-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-top: 2px solid #e2e8f0;
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .passengers-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .passenger-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .passenger-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .passenger-type-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .adult-badge { background: #e8f5e9; color: #2e7d32; }
        .child-badge { background: #e3f2fd; color: #1565c0; }
        .infant-badge { background: #f3e5f5; color: #7b1fa2; }

        .passenger-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            margin-bottom: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-confirmed { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3cd; color: #856404; }
        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-paid { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Details</h1>
            <p>Booking Reference: <?php echo $booking_ref; ?></p>
            <div style="margin-top: 10px;">
                <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                    Status: <?php echo ucfirst($booking['booking_status']); ?>
                </span>
                <span class="status-badge payment-<?php echo $booking['payment_status']; ?>">
                    Payment: <?php echo ucfirst($booking['payment_status']); ?>
                </span>
            </div>
        </div>

        <div class="booking-summary">
            <!-- Flight Details -->
            <div class="flight-details-card">
                <h2 class="card-title">
                    <i class="fas fa-plane"></i>
                    Flight Details
                </h2>

                <div class="flight-route">
                    <div class="route-city">
                        <div class="city-name"><?php echo htmlspecialchars($booking['departure_city']); ?></div>
                        <div class="city-details">
                            <?php echo date('M d, Y', strtotime($booking['departure_date'])); ?><br>
                            <?php echo date('H:i', strtotime($booking['departure_time'])); ?>
                        </div>
                    </div>

                    <div class="route-arrow">
                        <i class="fas fa-long-arrow-alt-right fa-2x"></i>
                    </div>

                    <div class="route-city">
                        <div class="city-name"><?php echo htmlspecialchars($booking['arrival_city']); ?></div>
                        <div class="city-details">
                            <?php 
                                $arrival_date = !empty($booking['arrival_date']) ? $booking['arrival_date'] : $booking['departure_date'];
                                echo date('M d, Y', strtotime($arrival_date)); 
                            ?><br>
                            <?php echo !empty($booking['arrival_time']) ? date('H:i', strtotime($booking['arrival_time'])) : '--:--'; ?>
                        </div>
                    </div>
                </div>

                <div class="flight-info-grid">
                    <div class="info-item">
                        <div class="info-label">Airline</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['airline_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Flight Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['flight_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?php echo $booking['duration'] ?? 'Direct'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Aircraft</div>
                        <div class="info-value"><?php echo $booking['aircraft_type'] ?? 'A320'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Class</div>
                        <div class="info-value"><?php echo ucfirst($booking['class'] ?? 'economy'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Baggage</div>
                        <div class="info-value"><?php echo $booking['baggage_allowance'] ?? '20kg'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Price Summary -->
            <div class="price-summary-card">
                <h2 class="card-title">
                    <i class="fas fa-receipt"></i>
                    Price Summary
                </h2>

                <div class="price-breakdown">
                    <div class="price-item">
                        <div class="price-label">Base Fare</div>
                        <div class="price-amount">PKR <?php echo number_format($booking['base_price'], 0); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label">Taxes & Fees</div>
                        <div class="price-amount">PKR <?php echo number_format($booking['taxes_fees'], 0); ?></div>
                    </div>
                </div>

                <div class="total-price">
                    <div>Total Amount</div>
                    <div>PKR <?php echo number_format($booking['total_amount'], 0); ?></div>
                </div>

                <div style="margin-top: 15px; font-size: 12px; color: #666;">
                    <p><strong>Booked on:</strong> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Passengers Section -->
        <div class="passengers-section">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Passenger Details
                <span style="font-size: 16px; color: #666; margin-left: auto;">
                    <?php echo count($passengers); ?> Passenger<?php echo count($passengers) > 1 ? 's' : ''; ?>
                </span>
            </h2>

            <?php foreach($passengers as $passenger): 
                $badge_class = '';
                switch($passenger['passenger_type']) {
                    case 'adult': $badge_class = 'adult-badge'; break;
                    case 'child': $badge_class = 'child-badge'; break;
                    case 'infant': $badge_class = 'infant-badge'; break;
                }
            ?>
            <div class="passenger-card">
                <div class="passenger-header">
                    <div>
                        <h3 style="margin-bottom: 5px;">
                            <?php echo htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']); ?>
                        </h3>
                        <span class="passenger-type-badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($passenger['passenger_type']); ?>
                        </span>
                    </div>
                </div>

                <div class="passenger-details">
                    <div class="detail-item">
                        <div class="detail-label">Date of Birth</div>
                        <div class="detail-value">
                            <?php echo date('M d, Y', strtotime($passenger['date_of_birth'])); ?>
                            (<?php echo floor((time() - strtotime($passenger['date_of_birth'])) / 31556926); ?> years)
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Gender</div>
                        <div class="detail-value"><?php echo ucfirst($passenger['gender']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Passport Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($passenger['passport_number']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Nationality</div>
                        <div class="detail-value"><?php echo htmlspecialchars($passenger['nationality']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Seat Preference</div>
                        <div class="detail-value"><?php echo $passenger['seat_preference'] ?? 'Any'; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="action-buttons">
            <a href="user_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            
            <a href="generate_ticket.php?booking_ref=<?php echo $booking_ref; ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-download"></i>
                Download E-Ticket
            </a>

            <?php if($booking['payment_status'] == 'pending'): ?>
            <a href="payment.php?booking_ref=<?php echo $booking_ref; ?>" class="btn btn-primary">
                <i class="fas fa-credit-card"></i>
                Make Payment
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>