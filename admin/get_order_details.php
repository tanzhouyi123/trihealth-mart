<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Order ID is required');
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.username, u.email, u.phone, u.address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('HTTP/1.1 404 Not Found');
    exit('Order not found');
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-0">
    <!-- Order Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="mb-3">Order Information</h6>
            <table class="table table-sm">
                <tr>
                    <th>Order ID:</th>
                    <td>#<?php echo $order['id']; ?></td>
                </tr>
                <tr>
                    <th>Date:</th>
                    <td><?php echo date('F d, Y H:i', strtotime($order['created_at'])); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <span class="badge bg-<?php 
                            echo $order['status'] === 'delivered' ? 'success' : 
                                ($order['status'] === 'cancelled' ? 'danger' : 'warning'); 
                        ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Payment Method:</th>
                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="mb-3">Customer Information</h6>
            <table class="table table-sm">
                <tr>
                    <th>Name:</th>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Shipping Address:</th>
                    <td><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Order Items -->
    <h6 class="mb-3">Order Items</h6>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($item['image']): ?>
                                <img src="../assets/images/products/<?php echo $item['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     class="img-thumbnail me-2" style="width: 50px;">
                            <?php endif; ?>
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </div>
                    </td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total Amount:</th>
                    <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div> 