<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get delivery man ID
$delivery_id = filter_input(INPUT_POST, 'delivery_id', FILTER_VALIDATE_INT);

if (!$delivery_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid delivery man ID']);
    exit();
}

try {
    // Check if delivery man exists
    $stmt = $conn->prepare("SELECT * FROM delivery_men WHERE id = ?");
    $stmt->execute([$delivery_id]);
    $delivery_man = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery_man) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Delivery man not found']);
        exit();
    }

    // Check if delivery man has any active orders
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE deliveryman_id = ? AND status IN ('pending', 'confirmed', 'processing')");
    $stmt->execute([$delivery_id]);
    $active_orders = $stmt->fetchColumn();

    if ($active_orders > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Cannot delete delivery man because they have {$active_orders} active order(s). Please reassign or complete the orders first."]);
        exit();
    }

    // Delete delivery man image if exists
    if ($delivery_man['image_path'] && file_exists('../' . $delivery_man['image_path'])) {
        unlink('../' . $delivery_man['image_path']);
    }

    // Update orders to remove delivery man assignment
    $stmt = $conn->prepare("UPDATE orders SET deliveryman_id = NULL WHERE deliveryman_id = ?");
    $stmt->execute([$delivery_id]);

    // Delete the delivery man
    $stmt = $conn->prepare("DELETE FROM delivery_men WHERE id = ?");
    if ($stmt->execute([$delivery_id])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Delivery man deleted successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to delete delivery man']);
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 