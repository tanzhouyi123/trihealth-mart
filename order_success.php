<?php
session_start();
require_once 'config/database.php';
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}
$order_id = intval($_GET['id']);

// Fetch order details including reference code
$stmt = $conn->prepare('SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?');
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5 text-center">
    <h2 class="mb-4 text-success"><i class="fas fa-check-circle"></i> Order Placed Successfully!</h2>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Order Details</h5>
                    <p class="lead">Your order has been placed successfully!</p>
                    <div class="row text-start">
                        <div class="col-md-6">
                            <p><strong>Order Number:</strong> #<?php echo $order['id']; ?></p>
                            <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Reference Code:</strong> <span class="text-primary fw-bold"><?php echo htmlspecialchars($order['reference_code']); ?></span></p>
                            <p><strong>Status:</strong> <span class="badge bg-warning"><?php echo ucfirst($order['status']); ?></span></p>
                            <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong> Please use the reference code <strong><?php echo htmlspecialchars($order['reference_code']); ?></strong> when making your payment. This helps us identify your payment quickly.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <a href="index.php" class="btn btn-primary me-2">Back to Home</a>
        <a href="profile.php" class="btn btn-outline-secondary">View My Orders</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 