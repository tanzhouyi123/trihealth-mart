<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

// Get user ID from request
$user_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$user_id) {
    die('User ID is required');
}

// Get user details
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_orders,
           SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found');
}

// Get recent orders
$stmt = $conn->prepare("
    SELECT o.*, 
           COUNT(oi.id) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-0">
    <!-- User Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-muted mb-2">Basic Information</h6>
            <table class="table table-sm">
                <tr>
                    <th style="width: 150px;">Username:</th>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></td>
                </tr>
                <tr>
                    <th>Address:</th>
                    <td><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></td>
                </tr>
                <tr>
                    <th>Role:</th>
                    <td>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Joined:</th>
                    <td><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted mb-2">Order Statistics</h6>
            <div class="row">
                <div class="col-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?php echo $user['total_orders']; ?></h3>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="mb-0">$<?php echo number_format($user['total_spent'] ?? 0, 2); ?></h3>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="mb-0">
                                <?php echo $user['last_order_date'] ? date('M d, Y', strtotime($user['last_order_date'])) : 'N/A'; ?>
                            </h3>
                            <small class="text-muted">Last Order</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3 class="mb-0">
                                $<?php echo $user['total_orders'] > 0 ? 
                                    number_format(($user['total_spent'] ?? 0) / $user['total_orders'], 2) : '0.00'; ?>
                            </h3>
                            <small class="text-muted">Average Order Value</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <h6 class="text-muted mb-3">Recent Orders</h6>
    <?php if (empty($recent_orders)): ?>
        <div class="alert alert-info">No orders found for this user.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                            <td><?php echo $order['total_items']; ?> items</td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($order['payment_method']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div> 