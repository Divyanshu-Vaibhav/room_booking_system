<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Release any expired blocks
releaseExpiredBlocks($conn);

// Get user's bookings
$stmt = $conn->prepare("SELECT b.*, r.name as room_name 
                        FROM bookings b 
                        JOIN rooms r ON b.room_id = r.id 
                        WHERE b.user_id = ? 
                        ORDER BY b.start_time DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Get user's waitlist entries
$stmt = $conn->prepare("SELECT w.*, r.name as room_name 
                        FROM waitlist w 
                        JOIN rooms r ON w.room_id = r.id 
                        WHERE w.user_id = ? AND w.status IN ('waiting', 'notified') 
                        ORDER BY w.start_time DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$waitlist_result = $stmt->get_result();

// Handle booking from waitlist
if (isset($_GET['book_from_waitlist']) && isset($_GET['waitlist_id'])) {
    $waitlist_id = $_GET['waitlist_id'];
    
    // Get waitlist entry
    $stmt = $conn->prepare("SELECT * FROM waitlist WHERE id = ? AND user_id = ? AND status = 'notified'");
    $stmt->bind_param("ii", $waitlist_id, $_SESSION['user_id']);
    $stmt->execute();
    $waitlist_result_single = $stmt->get_result();
    
    if ($waitlist_result_single->num_rows > 0) {
        $waitlist_entry = $waitlist_result_single->fetch_assoc();
        
        // Check if room is still available
        if (isRoomAvailable($conn, $waitlist_entry['room_id'], $waitlist_entry['start_time'], $waitlist_entry['end_time'])) {
            // Block the room
            $booking_id = blockRoom(
                $conn, 
                $waitlist_entry['room_id'], 
                $_SESSION['user_id'], 
                $waitlist_entry['start_time'], 
                $waitlist_entry['end_time']
            );
            
            if ($booking_id) {
                // Update waitlist status
                $stmt = $conn->prepare("UPDATE waitlist SET status = 'booked' WHERE id = ?");
                $stmt->bind_param("i", $waitlist_id);
                $stmt->execute();
                
                // Redirect to confirm booking
                redirect('confirm_booking.php?booking_id=' . $booking_id);
            }
        } else {
            // Room is no longer available
            $error_message = "Sorry, this room is no longer available for the requested time slot.";
        }
    }
}
?>

<h2>My Bookings</h2>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">Bookings</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="waitlist-tab" data-bs-toggle="tab" data-bs-target="#waitlist" type="button" role="tab">Waitlist</button>
    </li>
</ul>

<div class="tab-content" id="myTabContent">
    <div class="tab-pane fade show active" id="bookings" role="tabpanel">
        <?php if ($bookings_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($booking['start_time'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></td>
                                <td>
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <span class="badge bg-success">Confirmed</span>
                                    <?php elseif ($booking['status'] === 'blocked'): ?>
                                        <span class="badge bg-warning">Blocked</span>
                                    <?php elseif ($booking['status'] === 'expired'): ?>
                                        <span class="badge bg-secondary">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking['status'] === 'blocked'): ?>
                                        <a href="confirm_booking.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">Complete Booking</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">You have no bookings yet.</div>
        <?php endif; ?>
    </div>
    
    <div class="tab-pane fade" id="waitlist" role="tabpanel">
        <?php if ($waitlist_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($waitlist = $waitlist_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($waitlist['room_name']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($waitlist['start_time'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($waitlist['start_time'])); ?> - <?php echo date('g:i A', strtotime($waitlist['end_time'])); ?></td>
                                <td>
                                    <?php if ($waitlist['status'] === 'waiting'): ?>
                                        <span class="badge bg-info">Waiting</span>
                                    <?php elseif ($waitlist['status'] === 'notified'): ?>
                                        <span class="badge bg-warning">Available Now</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($waitlist['status'] === 'notified'): ?>
                                        <a href="my_bookings.php?book_from_waitlist=1&waitlist_id=<?php echo $waitlist['id']; ?>" class="btn btn-sm btn-success">Book Now</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">You are not on any waitlists.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
