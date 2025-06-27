<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a deliveryman
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'deliveryman') {
    header('Location: ../login.php');
    exit();
}

// Get deliveryman details
require_once '../config/database.php';
$stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$deliveryman = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unread notifications count
$stmt = $conn->prepare('
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = ? AND user_type = "deliveryman" AND is_read = 0
');
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

$deliveryman_username = isset($_SESSION['deliveryman_username']) ? $_SESSION['deliveryman_username'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">TriHealth Mart Delivery</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php">Logout</a>
                </li>
            </ul>
            <span class="navbar-text ms-3">Hello, <?php echo htmlspecialchars($deliveryman_username); ?></span>
        </div>
    </div>
</nav>
<div style="height:60px;"></div> 