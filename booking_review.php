<?php
session_start();
include 'config.php';

// Check if user is logged in and booking data exists
if(!isset($_SESSION['user_logged_in']) || !isset($_SESSION['booking_data'])) {
    header('Location: flights.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

$booking_data = $_SESSION['booking_data'];
$flight = $booking_data['flight_data'];
$passenger_data = $booking_data['passenger_data'];
$num_adults = $booking_data['num_adults'];
$total_amount = $booking_data['total_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Booking - Hussain Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #2c3e50;
            line-height: 1.5;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px;
            background: #f8f9fa;
            min-height: 100vh;
            width: calc(100% - 250px);
        }

        .top-bar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #e1e8ed;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            border: 3px solid #e1e8ed;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            padding: 10px 0;
            min-width: 200px;
            display: none;
            z-index: 1000;
            border: 1px solid #e1e8ed;
        }

        .profile-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: #2c3e50;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-divider {
            height: 1px;
            background: #e1e8ed;
            margin: 5px 0;
        }

        .content-area {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }

        .review-container {
            display: grid;
            gap: 25px;
        }

        .review-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }

        .flight-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .detail-label {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
        }

        .highlight-value {
            color: #27ae60;
            font-weight: 700;
        }

        .passenger-grid {
            display: grid;
            gap: 15px;
        }

        .passenger-card {
            background: #f8f9fa;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 20px;
        }

        .passenger-header {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .passenger-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .passenger-detail {
            display: flex;
            flex-direction: column;
        }

        .passenger-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .passenger-value {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .price-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e1e8ed;
        }

        .price-total {
            border-top: 2px solid #e1e8ed;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 700;
        }

        .total-value {
            font-size: 18px;
            color: #27ae60;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #7f8c8d;
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #e74c3c;
        }

        .modal-body {
            padding: 25px;
        }

        .passenger-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .passenger-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e1e8ed;
        }

        .passenger-item-header {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e8ed;
        }

        .passenger-item-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .detail-text {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e1e8ed;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #95a5a6;
            color: #95a5a6;
        }

        .btn-outline:hover {
            background: #95a5a6;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .flight-summary {
                grid-template-columns: 1fr;
            }
            
            .passenger-details {
                grid-template-columns: 1fr;
            }
            
            .passenger-item-details {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-bar">
                <div></div>
                <div class="user-profile">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                    <div class="user-avatar" id="userAvatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="bookings.php" class="dropdown-item">
                            <i class="fas fa-suitcase"></i> My Bookings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <div class="page-header">
                    <h1 class="page-title">Review Your Booking</h1>
                    <p class="page-subtitle">Please review all details before confirmation</p>
                </div>

                <div class="review-container">
                    <!-- Flight Details -->
                    <div class="review-card">
                        <h3 class="card-title">
                            <i class="fas fa-plane"></i>
                            Flight Details
                        </h3>
                        <div class="flight-summary">
                            <div class="detail-item">
                                <span class="detail-label">Airline</span>
                                <span class="detail-value"><?php echo htmlspecialchars($flight['airline_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Flight Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($flight['flight_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Flight Type</span>
                                <span class="detail-value"><?php echo ucwords(str_replace('_', ' ', $flight['flight_type'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Available Seats</span>
                                <span class="detail-value"><?php echo $flight['available_seats']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Details -->
                    <div class="review-card">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            Passenger Details (<?php echo $num_adults; ?> Adult<?php echo $num_adults > 1 ? 's' : ''; ?>)
                        </h3>
                        <div class="passenger-grid">
                            <?php foreach($passenger_data as $index => $passenger): ?>
                            <div class="passenger-card">
                                <div class="passenger-header">
                                    <i class="fas fa-user"></i>
                                    Passenger <?php echo $index + 1; ?> <?php echo $index === 0 ? '(Primary)' : ''; ?>
                                </div>
                                <div class="passenger-details">
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Title & Name</span>
                                        <span class="passenger-value"><?php echo htmlspecialchars($passenger['title'] . ' ' . $passenger['full_name']); ?></span>
                                    </div>
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Gender</span>
                                        <span class="passenger-value"><?php echo htmlspecialchars($passenger['gender']); ?></span>
                                    </div>
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Date of Birth</span>
                                        <span class="passenger-value"><?php echo date('d M Y', strtotime($passenger['date_of_birth'])); ?></span>
                                    </div>
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Nationality</span>
                                        <span class="passenger-value"><?php echo htmlspecialchars($passenger['nationality']); ?></span>
                                    </div>
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Passport Number</span>
                                        <span class="passenger-value"><?php echo htmlspecialchars($passenger['passport_number']); ?></span>
                                    </div>
                                    <div class="passenger-detail">
                                        <span class="passenger-label">Passport Expiry</span>
                                        <span class="passenger-value"><?php echo date('d M Y', strtotime($passenger['passport_expiry'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Summary -->
                    <div class="review-card">
                        <h3 class="card-title">
                            <i class="fas fa-receipt"></i>
                            Price Summary
                        </h3>
                        <div class="price-summary">
                            <div class="price-item">
                                <span class="detail-label">Price per Adult</span>
                                <span class="detail-value">PKR <?php echo number_format($flight['price'], 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span class="detail-label">Number of Adults</span>
                                <span class="detail-value"><?php echo $num_adults; ?></span>
                            </div>
                            <div class="price-item price-total">
                                <span class="detail-label">Total Amount</span>
                                <span class="total-value">PKR <?php echo number_format($total_amount, 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="bookings.php?flight_id=<?php echo $booking_data['flight_id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        Edit Details
                    </a>
                    
                    <button onclick="openModal()" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        View All Passengers
                    </button>
                    
                    <form method="POST" action="process_booking.php" style="display: inline;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i>
                            Confirm & Proceed to Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for All Passengers -->
    <div class="modal" id="passengerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-users"></i>
                    All Passenger Details
                </h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="passenger-list">
                    <?php foreach($passenger_data as $index => $passenger): ?>
                    <div class="passenger-item">
                        <div class="passenger-item-header">
                            <i class="fas fa-user"></i>
                            Passenger <?php echo $index + 1; ?> <?php echo $index === 0 ? '(Primary)' : ''; ?>
                        </div>
                        <div class="passenger-item-details">
                            <div class="detail-row">
                                <span class="detail-label">Title & Name</span>
                                <span class="detail-text"><?php echo htmlspecialchars($passenger['title'] . ' ' . $passenger['full_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Gender</span>
                                <span class="detail-text"><?php echo htmlspecialchars($passenger['gender']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date of Birth</span>
                                <span class="detail-text"><?php echo date('d M Y', strtotime($passenger['date_of_birth'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Nationality</span>
                                <span class="detail-text"><?php echo htmlspecialchars($passenger['nationality']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Passport Number</span>
                                <span class="detail-text"><?php echo htmlspecialchars($passenger['passport_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Passport Expiry</span>
                                <span class="detail-text"><?php echo date('d M Y', strtotime($passenger['passport_expiry'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
                <a href="bookings.php?flight_id=<?php echo $booking_data['flight_id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i>
                    Edit Details
                </a>
            </div>
        </div>
    </div>

    <script>
        // Profile dropdown functionality
        document.getElementById('userAvatar').addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.toggle('show');
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.getElementById('userAvatar');
            if (!avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Modal functionality
        function openModal() {
            document.getElementById('passengerModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('passengerModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('passengerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>