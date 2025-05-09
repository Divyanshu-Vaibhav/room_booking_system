<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Release any expired blocks
releaseExpiredBlocks($conn);

// Check if room_id is provided
if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    redirect('rooms.php');
}

$room_id = $_GET['room_id'];

// Get room details
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('rooms.php');
}

$room = $result->fetch_assoc();

$errors = [];
$success = false;
$booking_id = null;
$waitlist_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    
    // Validation
    if (empty($date) || empty($start_time) || empty($end_time)) {
        $errors[] = "All fields are required";
    }
    
    // Convert to datetime format
    $start_datetime = $date . ' ' . $start_time . ':00';
    $end_datetime = $date . ' ' . $end_time . ':00';
    
    // Check if start time is before end time
    if (strtotime($start_datetime) >= strtotime($end_datetime)) {
        $errors[] = "End time must be after start time";
    }
    
    // If no errors, check availability and block the room
    if (empty($errors)) {
        if (isRoomAvailable($conn, $room_id, $start_datetime, $end_datetime)) {
            // Room is available, block it
            $booking_id = blockRoom($conn, $room_id, $_SESSION['user_id'], $start_datetime, $end_datetime);
            
            if ($booking_id) {
                $success = true;
            } else {
                $errors[] = "Failed to block room. Please try again.";
            }
        } else {
            // Room is not available, add to waitlist
            $waitlist_id = addToWaitlist($conn, $room_id, $_SESSION['user_id'], $start_datetime, $end_datetime);
            
            if ($waitlist_id) {
                $errors[] = "Room is not available for the selected time. You have been added to the waitlist.";
            } else {
                $errors[] = "Room is not available and we couldn't add you to the waitlist. Please try again.";
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Book Room: <?php echo htmlspecialchars($room['name']); ?></h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Room has been blocked for you for 5 minutes. Please complete your booking.
                        <a href="confirm_booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary mt-3">Confirm Booking</a>
                    </div>
                <?php elseif ($waitlist_id): ?>
                    <div class="alert alert-warning">
                        Room is not available for the selected time. You have been added to the waitlist and will be notified if it becomes available.
                        <a href="rooms.php" class="btn btn-primary mt-3">Back to Rooms</a>
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
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Book Now</button>
                        <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
