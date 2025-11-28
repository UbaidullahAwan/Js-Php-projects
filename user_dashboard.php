<?php
session_start();
include 'config.php';

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's bookings
try {
    $sql = "SELECT b.*, f.flight_number, f.departure_city, f.arrival_city, 
                   f.departure_date, f.departure_time, a.airline_name,
                   (SELECT COUNT(*) FROM passengers p WHERE p.booking_id = b.booking_id) as passenger_count
            FROM bookings b 
            JOIN flights f ON b.flight_id = f.flight_id 
            JOIN airlines a ON f.airline_id = a.airline_id 
            WHERE b.user_id = ? 
            ORDER BY b.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            max-width: 1200px;
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

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        .booking-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .booking-ref {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-confirmed { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-paid { background: #e8f5e9; color: #2e7d32; }

        .flight-route {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .route-city {
            text-align: center;
        }

        .city-name {
            font-size: 16px;
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
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-bookings i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .navigation {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 25px;
        }

        .nav-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: #667eea;
            color: white;
        }

        .nav-btn.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Dashboard</h1>
            <p>Manage your flight bookings</p>
        </div>

        <div class="welcome-section">
            <h2>Welcome back, <?php echo $_SESSION['user_name'] ?? 'User'; ?>!</h2>
            <p>You have <?php echo count($bookings); ?> booking<?php echo count($bookings) !== 1 ? 's' : ''; ?> in your account</p>
        </div>

        <div class="navigation">
            <a href="flights.php" class="nav-btn">
                <i class="fas fa-plane"></i> Book New Flight
            </a>
            <a href="user_dashboard.php" class="nav-btn active">
                <i class="fas fa-ticket-alt"></i> My Bookings
            </a>
            <a href="logout.php" class="nav-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <?php if(empty($bookings)): ?>
            <div class="no-bookings">
                <i class="fas fa-ticket-alt"></i>
                <h3>No Bookings Yet</h3>
                <p>You haven't made any flight bookings yet.</p>
                <a href="flights.php" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plane"></i> Book Your First Flight
                </a>
            </div>
        <?php else: ?>
            <?php foreach($bookings as $booking): ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-ref">
                        Booking #<?php echo $booking['booking_reference']; ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                            <?php echo ucfirst($booking['booking_status']); ?>
                        </span>
                        <span class="status-badge payment-<?php echo $booking['payment_status']; ?>">
                            Payment: <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                    </div>
                </div>

                <div class="flight-route">
                    <div class="route-city">
                        <div class="city-name"><?php echo htmlspecialchars($booking['departure_city']); ?></div>
                        <div class="city-details">
                            <?php echo date('M d, Y', strtotime($booking['departure_date'])); ?><br>
                            <?php echo date('H:i', strtotime($booking['departure_time'])); ?>
                        </div>
                    </div>

                    <div class="route-arrow">
                        <i class="fas fa-long-arrow-alt-right fa-lg"></i>
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

                <div class="booking-details">
                    <div class="detail-item">
                        <div class="detail-label">Airline</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['airline_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Flight</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['flight_number']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Passengers</div>
                        <div class="detail-value"><?php echo $booking['passenger_count']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value">PKR <?php echo number_format($booking['total_amount'], 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Booked On</div>
                        <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="generate_ticket.php?booking_ref=<?php echo $booking['booking_reference']; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-download"></i> E-Ticket
                    </a>
                    <?php if($booking['payment_status'] == 'pending'): ?>
                    <a href="payment.php?booking_ref=<?php echo $booking['booking_reference']; ?>" class="btn btn-info">
                        <i class="fas fa-credit-card"></i> Make Payment
                    </a>
                    <?php endif; ?>
                    <a href="booking_details.php?booking_ref=<?php echo $booking['booking_reference']; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>