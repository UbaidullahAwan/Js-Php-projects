<?php
session_start();
include 'config.php';

// Check if user is logged in
if(!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get flight and passenger data
$flight_id = isset($_GET['flight_id']) ? intval($_GET['flight_id']) : 0;
$adults = isset($_GET['adults']) ? intval($_GET['adults']) : 1;
$children = isset($_GET['children']) ? intval($_GET['children']) : 0;
$infants = isset($_GET['infants']) ? intval($_GET['infants']) : 0;

if($flight_id == 0) {
    die('Invalid flight selection.');
}

// Fetch flight details
try {
    $sql = "SELECT 
                f.*, 
                a.airline_name,
                a.airline_code,
                a.logo_path
            FROM flights f 
            LEFT JOIN airlines a ON f.airline_id = a.airline_id 
            WHERE f.flight_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$flight_id]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$flight) {
        die('Flight not found in database.');
    }
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Store passenger data in session
    $_SESSION['passenger_data'] = $_POST['passengers'];
    $_SESSION['booking_flight_id'] = $flight_id;
    $_SESSION['passenger_counts'] = [
        'adults' => $adults,
        'children' => $children,
        'infants' => $infants
    ];
    
    header('Location: booking_review.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Details - Flight Booking</title>
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

        .airline-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .airline-logo {
            width: 50px;
            height: 50px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        .airline-name {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .flight-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .flight-summary h2 {
            margin-bottom: 15px;
            font-size: 24px;
        }

        .flight-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid rgba(255, 255, 255, 0.3);
        }

        .detail-label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 700;
        }

        .passenger-forms {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 25px;
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

        .passenger-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .passenger-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .passenger-number {
            background: #667eea;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
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

        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #666;
            font-size: 14px;
        }

        .route-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }

        .route-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 600;
        }

        .route-arrow {
            color: #2196f3;
            margin: 0 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="airline-header">
                <div class="airline-logo"><?php echo substr($flight['airline_name'] ?? 'AI', 0, 2); ?></div>
                <div class="airline-name"><?php echo htmlspecialchars($flight['airline_name'] ?? 'AIRSIAL'); ?></div>
            </div>
            <p>Enter Passenger Details</p>
        </div>

        <!-- Flight Summary -->
        <div class="flight-summary">
            <h2><i class="fas fa-plane"></i> Flight Summary</h2>
            
            <?php if(!empty($flight['departure_city']) && !empty($flight['arrival_city'])): ?>
            <div class="route-info">
                <div class="route-display">
                    <div class="departure">
                        <div class="detail-label">Departure</div>
                        <div class="detail-value"><?php echo htmlspecialchars($flight['departure_city']); ?></div>
                        <?php if(!empty($flight['departure_time'])): ?>
                        <div class="detail-label">Time</div>
                        <div class="detail-value">
                            <?php echo date('H:i', strtotime($flight['departure_time'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="route-arrow">
                        <i class="fas fa-long-arrow-alt-right fa-2x"></i>
                    </div>
                    
                    <div class="arrival">
                        <div class="detail-label">Arrival</div>
                        <div class="detail-value"><?php echo htmlspecialchars($flight['arrival_city']); ?></div>
                        <?php if(!empty($flight['arrival_time'])): ?>
                        <div class="detail-label">Time</div>
                        <div class="detail-value">
                            <?php echo date('H:i', strtotime($flight['arrival_time'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="flight-details">
                <div class="detail-item">
                    <div class="detail-label">Flight Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($flight['flight_number']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">
                        <?php echo date('M d, Y', strtotime($flight['departure_date'] ?? 'now')); ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Passengers</div>
                    <div class="detail-value">
                        <?php 
                            $total_passengers = $adults + $children + $infants;
                            echo $total_passengers . ' Passenger' . ($total_passengers > 1 ? 's' : '');
                        ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Total Price</div>
                    <div class="detail-value">
                        PKR <?php echo number_format($flight['price'] * $adults, 0); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Passenger Forms -->
        <div class="passenger-forms">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Passenger Details
            </h2>

            <form method="POST" id="passengerDetailsForm">
                <?php
                $passenger_index = 0;
                
                // Adult passengers
                for($i = 0; $i < $adults; $i++): 
                    $passenger_index++;
                ?>
                <div class="passenger-form">
                    <div class="passenger-header">
                        <div class="passenger-number"><?php echo $passenger_index; ?></div>
                        <h3>Adult Passenger <?php echo $i + 1; ?></h3>
                        <span class="passenger-type-badge adult-badge">Adult</span>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">First Name</label>
                            <input type="text" name="passengers[<?php echo $i; ?>][first_name]" required 
                                   placeholder="Enter first name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Last Name</label>
                            <input type="text" name="passengers[<?php echo $i; ?>][last_name]" required 
                                   placeholder="Enter last name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Date of Birth</label>
                            <input type="date" name="passengers[<?php echo $i; ?>][date_of_birth]" required 
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Gender</label>
                            <select name="passengers[<?php echo $i; ?>][gender]" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Passport Number</label>
                            <input type="text" name="passengers[<?php echo $i; ?>][passport_number]" required 
                                   placeholder="Passport number">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Nationality</label>
                            <input type="text" name="passengers[<?php echo $i; ?>][nationality]" required 
                                   placeholder="Nationality">
                        </div>
                    </div>
                    <input type="hidden" name="passengers[<?php echo $i; ?>][passenger_type]" value="adult">
                </div>
                <?php endfor; ?>

                <!-- Child passengers -->
                <?php for($i = 0; $i < $children; $i++): 
                    $passenger_index++;
                    $adult_index = $adults + $i;
                ?>
                <div class="passenger-form">
                    <div class="passenger-header">
                        <div class="passenger-number"><?php echo $passenger_index; ?></div>
                        <h3>Child Passenger <?php echo $i + 1; ?></h3>
                        <span class="passenger-type-badge child-badge">Child (2-11)</span>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">First Name</label>
                            <input type="text" name="passengers[<?php echo $adult_index; ?>][first_name]" required 
                                   placeholder="Enter first name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Last Name</label>
                            <input type="text" name="passengers[<?php echo $adult_index; ?>][last_name]" required 
                                   placeholder="Enter last name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Date of Birth</label>
                            <input type="date" name="passengers[<?php echo $adult_index; ?>][date_of_birth]" required 
                                   max="<?php echo date('Y-m-d', strtotime('-2 years')); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('-11 years')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Gender</label>
                            <select name="passengers[<?php echo $adult_index; ?>][gender]" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="passengers[<?php echo $adult_index; ?>][passenger_type]" value="child">
                    <input type="hidden" name="passengers[<?php echo $adult_index; ?>][passport_number]" value="CHILD<?php echo $adult_index + 1; ?>">
                    <input type="hidden" name="passengers[<?php echo $adult_index; ?>][nationality]" value="Same as Adult">
                </div>
                <?php endfor; ?>

                <!-- Infant passengers -->
                <?php for($i = 0; $i < $infants; $i++): 
                    $passenger_index++;
                    $infant_index = $adults + $children + $i;
                ?>
                <div class="passenger-form">
                    <div class="passenger-header">
                        <div class="passenger-number"><?php echo $passenger_index; ?></div>
                        <h3>Infant Passenger <?php echo $i + 1; ?></h3>
                        <span class="passenger-type-badge infant-badge">Infant (0-2)</span>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">First Name</label>
                            <input type="text" name="passengers[<?php echo $infant_index; ?>][first_name]" required 
                                   placeholder="Enter first name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Last Name</label>
                            <input type="text" name="passengers[<?php echo $infant_index; ?>][last_name]" required 
                                   placeholder="Enter last name">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Date of Birth</label>
                            <input type="date" name="passengers[<?php echo $infant_index; ?>][date_of_birth]" required 
                                   max="<?php echo date('Y-m-d'); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('-2 years')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Gender</label>
                            <select name="passengers[<?php echo $infant_index; ?>][gender]" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="passengers[<?php echo $infant_index; ?>][passenger_type]" value="infant">
                    <input type="hidden" name="passengers[<?php echo $infant_index; ?>][passport_number]" value="INFANT<?php echo $infant_index + 1; ?>">
                    <input type="hidden" name="passengers[<?php echo $infant_index; ?>][nationality]" value="Same as Adult">
                </div>
                <?php endfor; ?>

                <div class="action-buttons">
                    <a href="bookings.php?flight_id=<?php echo $flight_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Booking
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Review Booking
                    </button>
                </div>
            </form>
        </div>
        
        <div class="copyright">
            Copyright © 2025 All Rights Reserved
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('passengerDetailsForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *.');
            }
        });

        // Real-time validation
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = '#e74c3c';
                } else {
                    this.style.borderColor = '#ddd';
                }
            });
        });
    </script>
</body>
</html>