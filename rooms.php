<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

releaseExpiredBlocks($conn);

// Get all rooms
$sql = "SELECT * FROM rooms ORDER BY name";
$result = $conn->query($sql);
?>

<h2>Available Rooms</h2>

<div class="row">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($room = $result->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($room['name']); ?></h5>
                        <p class="card-text">Capacity: <?php echo $room['capacity']; ?> people</p>
                        <a href="booking.php?room_id=<?php echo $room['id']; ?>" class="btn btn-primary">Book Now</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">No rooms available at the moment.</div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
