<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to a specific page
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
        }
    }
    
    return $released_count;
}
?>
