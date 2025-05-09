<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    redirect('rooms.php');
}

$booking_id = $_GET['booking_id'];

// Get booking details
$stmt = $conn->prepare("SELECT b.*, r.name as room_name 
                        FROM bookings b 
                        JOIN rooms r ON b.room_id = r.id 
                        WHERE b.id = ? AND b.user_id = ? AND b.status = 'blocked'");
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('rooms.php');
}

$booking = $result->fetch_assoc();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate payment process (always successful in this simplified version)
    $payment_success = true;
    
    if ($payment_success) {
        // Confirm the booking
        if (confirmBooking($conn, $booking_id, $_SESSION['user_id'])) {
            $success = true;
            
            // Check if there are any waitlist entries for this time slot that should be notified
            // This prevents someone from getting on the waitlist after the room is already booked
            $sql = "UPDATE waitlist SET status = 'expired' 
                    WHERE room_id = ? AND start_time = ? AND end_time = ? AND status = 'waiting'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $booking['room_id'], $booking['start_time'], $booking['end_time']);
            $stmt->execute();
        } else {
            $errors[] = "Failed to confirm booking. It may have expired.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Confirm Booking</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Your booking has been confirmed!
                        <a href="my_bookings.php" class="btn btn-primary mt-3">View My Bookings</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <h5>Booking Details</h5>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($booking['room_name']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($booking['start_time'])); ?></p>
                    <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-warning">Blocked</span></p>
                    <p><strong>Block Expires:</strong> <?php echo date('g:i:s A', strtotime($booking['block_expires_at'])); ?></p>
                    
                    <div class="alert alert-info">
                        This room is blocked for you for 5 minutes. Please complete your booking to confirm.
                    </div>
                    
                    <form method="POST">
                        <h5 class="mt-4">Confirm Booking</h5>
                        <p>Click the button below to confirm your booking:</p>
                        <button type="submit" class="btn btn-primary">Confirm Booking</button>
                        <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
