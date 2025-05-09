<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
require_once 'config/database.php';
require_once 'includes/header.php';
// Release any expired blocks
releaseExpiredBlocks($conn);
?>

<div class="jumbotron bg-light p-5 rounded">
    <h1 class="display-4">Welcome to Room Booking System</h1>
    <p class="lead">Book rooms easily and efficiently with our system.</p>
    <hr class="my-4">
    <p>Our system allows you to book rooms, manage your bookings, and get notified when rooms become available.</p>
    <?php if (isLoggedIn()): ?>
        <a class="btn btn-primary btn-lg" href="rooms.php" role="button">Book a Room</a>
    <?php else: ?>
        <a class="btn btn-primary btn-lg" href="login.php" role="button">Login to Book</a>
        <a class="btn btn-outline-primary btn-lg" href="register.php" role="button">Register</a>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
