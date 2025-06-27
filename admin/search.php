<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
$query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10;

if (empty($query)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit();
}

try {
    switch ($type) {
        case 'products':
            $results = searchProducts($query, $limit, $conn);
            break;
        case 'categories':
            $results = searchCategories($query, $limit, $conn);
            break;
        case 'orders':
            $results = searchOrders($query, $limit, $conn);
            break;
        case 'users':
            $results = searchUsers($query, $limit, $conn);
            break;
        case 'delivery_men':
            $results = searchDeliveryMen($query, $limit, $conn);
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid search type']);
            exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => $results]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function searchProducts($query, $limit, $conn) {
    $search_term = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?
        ORDER BY p.name
        LIMIT ?
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchCategories($query, $limit, $conn) {
    $search_term = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE c.name LIKE ? OR c.description LIKE ?
        GROUP BY c.id
        ORDER BY c.name
        LIMIT ?
    ");
    $stmt->execute([$search_term, $search_term, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchOrders($query, $limit, $conn) {
    $search_term = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT o.*, u.username as customer_name, u.phone as customer_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id LIKE ? OR u.username LIKE ? OR u.phone LIKE ?
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchUsers($query, $limit, $conn) {
    $search_term = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT u.*, COUNT(o.id) as total_orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
        GROUP BY u.id
        ORDER BY u.username
        LIMIT ?
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchDeliveryMen($query, $limit, $conn) {
    $search_term = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT dm.*, COUNT(o.id) as total_orders
        FROM delivery_men dm
        LEFT JOIN orders o ON dm.id = o.deliveryman_id
        WHERE dm.username LIKE ? OR dm.email LIKE ? OR dm.phone LIKE ?
        GROUP BY dm.id
        ORDER BY dm.username
        LIMIT ?
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?> 