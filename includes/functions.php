<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirecting 
function redirect($url) {
    header("Location: $url");
    exit();
}

// Check if a room is available for the given time slot
function isRoomAvailable($conn, $room_id, $start_time, $end_time) {
    $sql = "SELECT * FROM bookings 
            WHERE room_id = ? 
            AND status IN ('blocked', 'confirmed') 
            AND ((start_time <= ? AND end_time > ?) 
                OR (start_time < ? AND end_time >= ?) 
                OR (start_time >= ? AND end_time <= ?))";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $room_id, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows === 0;
}

// Block a room for a user
function blockRoom($conn, $room_id, $user_id, $start_time, $end_time) {
    // Set block expiry time (5 minutes from now)
    $block_expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    $sql = "INSERT INTO bookings (room_id, user_id, start_time, end_time, status, block_expires_at) 
            VALUES (?, ?, ?, ?, 'blocked', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $room_id, $user_id, $start_time, $end_time, $block_expires_at);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

// Confirm a booking
function confirmBooking($conn, $booking_id, $user_id) {
    $sql = "UPDATE bookings SET status = 'confirmed' 
            WHERE id = ? AND user_id = ? AND status = 'blocked'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
}

// Add user to waitlist
function addToWaitlist($conn, $room_id, $user_id, $start_time, $end_time) {
    $sql = "INSERT INTO waitlist (room_id, user_id, start_time, end_time) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $room_id, $user_id, $start_time, $end_time);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}
// Process waitlist for a room
function processWaitlist($conn, $room_id, $start_time, $end_time) {
    // Find the first person in the waitlist for this room and time
    $sql = "SELECT id, user_id FROM waitlist 
            WHERE room_id = ? AND start_time = ? AND end_time = ? AND status = 'waiting' 
            ORDER BY id ASC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $room_id, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Update waitlist entry to notified
        $update_sql = "UPDATE waitlist SET status = 'notified' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $row['id']);
        $update_stmt->execute();
        
        // In a real system, you would send an email or notification here
        return true;
    }
    
    return false;
}


// Release expired blocks
function releaseExpiredBlocks($conn) {
    $now = date('Y-m-d H:i:s');
    
    // Get expired blocks
    $sql = "SELECT id, room_id, start_time, end_time FROM bookings 
            WHERE status = 'blocked' AND block_expires_at < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $released_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Mark the booking as expired
        $update_sql = "UPDATE bookings SET status = 'expired' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $row['id']);
        if ($update_stmt->execute()) {
            $released_count++;
            processWaitlist($conn, $row['room_id'], $row['start_time'], $row['end_time']);

        }
    }
    
    return $released_count;
}
?>
