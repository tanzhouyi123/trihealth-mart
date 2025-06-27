<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: profile.php');
    exit();
}
$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
// 获取订单信息
$stmt = $conn->prepare('SELECT o.*, d.username as deliveryman_name, d.phone as deliveryman_phone FROM orders o LEFT JOIN delivery_men d ON o.deliveryman_id = d.id WHERE o.id = ? AND o.user_id = ?');
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header('Location: profile.php');
    exit();
}
// 获取订单商品
$stmt = $conn->prepare('SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?');
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <?php if (isset($_GET['message'])): ?>
    <div class="alert alert-success mb-3"><?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>
    <h2 class="mb-4">Order #<?php echo $order['id']; ?></h2>
    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Shipping Info</h5>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
            <p><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <?php if ($order['reference_code']): ?>
            <p><strong>Reference Code:</strong> <span class="text-primary fw-bold"><?php echo htmlspecialchars($order['reference_code']); ?></span></p>
            <?php endif; ?>
            <?php
            // 查询二维码
            $qrcode = '';
            if ($order['payment_method']) {
                $stmt = $conn->prepare('SELECT qrcode_image FROM payment_methods WHERE name = ?');
                $stmt->execute([$order['payment_method']]);
                $qrcode = $stmt->fetchColumn();
            }
            if ($qrcode): ?>
            <div class="mb-3"><label class="form-label">Scan to Pay</label><br><img src="<?php echo htmlspecialchars($qrcode); ?>" style="max-width:200px;max-height:200px;" class="border rounded"></div>
            <?php endif; ?>
            <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
            <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
            <?php if ($order['deliveryman_name']): ?>
            <p><strong>Delivery Man:</strong> <?php echo htmlspecialchars($order['deliveryman_name']); ?> (<?php echo htmlspecialchars($order['deliveryman_phone']); ?>)</p>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h5>Order Summary</h5>
            <ul class="list-group mb-3">
                <?php foreach ($items as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?php 
                        $image_path = '';
                        if ($item['image']) {
                            if (strpos($item['image'], 'uploads/') === 0) {
                                $image_path = $item['image'];
                            } elseif (strpos($item['image'], 'assets/') === 0) {
                                $image_path = $item['image'];
                            } else {
                                $image_path = "assets/images/products/" . $item['image'];
                            }
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>" style="width:40px;" class="me-2">
                        <?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?>
                    </div>
                    <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                    Total <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                </li>
            </ul>
        </div>
    </div>
    <?php if (in_array($order['status'], ['pending','processing'])): ?>
    <form method="POST" class="mb-3">
        <button type="submit" name="cancel_order" class="btn btn-danger">Cancel Order</button>
    </form>
    <?php endif; ?>
    <a href="profile.php" class="btn btn-secondary">Back to My Orders</a>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
if (isset($_POST['cancel_order'])) {
    $stmt = $conn->prepare('UPDATE orders SET status = "cancelled" WHERE id = ? AND user_id = ? AND status IN ("pending","processing")');
    $stmt->execute([$order_id, $user_id]);
    header('Location: order_detail.php?id=' . $order_id . '&message=Order cancelled');
    exit();
}
?>
</body>
</html> 