<?php
include 'config.php';

if(!isset($_GET['route_id'])) {
    die("Invalid route");
}

$route_id = $_GET['route_id'];

// Get flight details
$flight_sql = "SELECT fr.*, f.flight_number, a.airline_name 
               FROM flight_routes fr 
               JOIN flights f ON fr.flight_id = f.flight_id 
               JOIN airlines a ON f.airline_id = a.airline_id 
               WHERE fr.route_id = ?";
$flight_stmt = $pdo->prepare($flight_sql);
$flight_stmt->execute([$route_id]);
$flight = $flight_stmt->fetch(PDO::FETCH_ASSOC);

// Get prices
$price_sql = "SELECT * FROM flight_rates WHERE route_id = ?";
$price_stmt = $pdo->prepare($price_sql);
$price_stmt->execute([$route_id]);
$prices = $price_stmt->fetchAll(PDO::FETCH_ASSOC);

if($_POST['book']) {
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Create booking
        $booking_sql = "INSERT INTO multi_city_bookings (user_id, total_amount, booking_date, booking_status) 
                       VALUES (?, ?, NOW(), 'confirmed')";
        $booking_stmt = $pdo->prepare($booking_sql);
        $booking_stmt->execute([1, $_POST['total_amount']]); // user_id 1 for demo
        $booking_id = $pdo->lastInsertId();
        
        // 2. Add booking segment
        $segment_sql = "INSERT INTO booking_segments (booking_id, route_id, segment_order, travel_date) 
                       VALUES (?, ?, 1, ?)";
        $segment_stmt = $pdo->prepare($segment_sql);
        $segment_stmt->execute([$booking_id, $route_id, $_POST['travel_date']]);
        
        // 3. Add passengers
        for($i = 0; $i < $_POST['passenger_count']; $i++) {
            $passenger_sql = "INSERT INTO passengers (booking_id, first_name, last_name, date_of_birth, passport_number) 
                             VALUES (?, ?, ?, ?, ?)";
            $passenger_stmt = $pdo->prepare($passenger_sql);
            $passenger_stmt->execute([
                $booking_id,
                $_POST['passengers'][$i]['first_name'],
                $_POST['passengers'][$i]['last_name'],
                $_POST['passengers'][$i]['dob'],
                $_POST['passengers'][$i]['passport']
            ]);
        }
        
        // 4. Create payment record
        $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_status) 
                       VALUES (?, ?, NOW(), 'completed')";
        $payment_stmt = $pdo->prepare($payment_sql);
        $payment_stmt->execute([$booking_id, $_POST['total_amount']]);
        
        // 5. Send email notification
        $email_sql = "INSERT INTO email_notifications (booking_id, user_id, to_email, subject, email_type, status) 
                     VALUES (?, 1, ?, 'Booking Confirmation', 'booking_confirmation', 'pending')";
        $email_stmt = $pdo->prepare($email_sql);
        $email_stmt->execute([$booking_id, 'customer@email.com']);
        
        $pdo->commit();
        header("Location: confirmation.php?booking_id=" . $booking_id);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        echo "Booking failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Flight</title>
</head>
<body>
    <h2>Book Flight: <?= $flight['airline_name'] ?> - <?= $flight['flight_number'] ?></h2>
    
    <form method="POST">
        <input type="hidden" name="total_amount" id="total_amount">
        
        <h3>Select Class:</h3>
        <?php foreach($prices as $price): ?>
        <div>
            <input type="radio" name="class_type" value="<?= $price['class_type'] ?>" 
                   data-price="<?= $price['total_price'] ?>" onchange="calculateTotal()">
            <?= $price['class_type'] ?> - $<?= $price['total_price'] ?>
        </div>
        <?php endforeach; ?>
        
        <h3>Passenger Details:</h3>
        <input type="number" name="passenger_count" id="passenger_count" min="1" max="10" value="1" onchange="generatePassengerFields()">
        
        <div id="passenger_fields"></div>
        
        <button type="submit" name="book">Confirm Booking</button>
    </form>

    <script>
        function generatePassengerFields() {
            const count = document.getElementById('passenger_count').value;
            let html = '';
            
            for(let i = 0; i < count; i++) {
                html += `
                <div>
                    <h4>Passenger ${i+1}</h4>
                    <input type="text" name="passengers[${i}][first_name]" placeholder="First Name" required>
                    <input type="text" name="passengers[${i}][last_name]" placeholder="Last Name" required>
                    <input type="date" name="passengers[${i}][dob]" required>
                    <input type="text" name="passengers[${i}][passport]" placeholder="Passport Number" required>
                </div>
                `;
            }
            
            document.getElementById('passenger_fields').innerHTML = html;
            calculateTotal();
        }
        
        function calculateTotal() {
            const passengerCount = document.getElementById('passenger_count').value;
            const selectedClass = document.querySelector('input[name="class_type"]:checked');
            
            if(selectedClass) {
                const price = selectedClass.getAttribute('data-price');
                const total = price * passengerCount;
                document.getElementById('total_amount').value = total;
            }
        }
        
        // Initialize passenger fields
        generatePassengerFields();
    </script>
</body>
</html>