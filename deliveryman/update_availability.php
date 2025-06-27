<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a deliveryman
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'deliveryman') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate is_available value
$is_available = filter_input(INPUT_POST, 'is_available', FILTER_VALIDATE_BOOLEAN);

// Update availability status
$stmt = $conn->prepare('UPDATE delivery_men SET is_available = ? WHERE id = ?');
if ($stmt->execute([$is_available ? 1 : 0, $_SESSION['user_id']])) {
    // Create notification for status change
    $title = $is_available ? 'You are now available for deliveries' : 'You are now unavailable for deliveries';
    $message = $is_available 
        ? 'Your status has been updated to available. You will now receive new delivery assignments.'
        : 'Your status has been updated to unavailable. You will not receive new delivery assignments.';
    
    $stmt = $conn->prepare('
        INSERT INTO notifications (user_id, user_type, type, title, message)
        VALUES (?, "deliveryman", "system", ?, ?)
    ');
    $stmt->execute([$_SESSION['user_id'], $title, $message]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update availability status']);
} 