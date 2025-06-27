<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// 获取购物车商品
$stmt = $conn->prepare('SELECT c.*, p.name, p.price, p.image FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?');
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
// 计算总价
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Shopping Cart</h2>
    <div class="table-responsive mb-4">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td>
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
                        <img src="<?php echo htmlspecialchars($image_path); ?>" style="width:50px;" class="me-2">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>
                        <form method="POST" action="update_cart.php" class="d-inline">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="99" style="width:60px;display:inline-block;">
                            <button type="submit" class="btn btn-sm btn-secondary ms-1">Update</button>
                        </form>
                    </td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    <td>
                        <form method="POST" action="remove_from_cart.php" class="d-inline">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cart_items)): ?>
                <tr><td colspan="5" class="text-center text-muted">Your cart is empty.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">
        <h4>Total: $<?php echo number_format($total, 2); ?></h4>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <a href="checkout.php" class="btn btn-primary btn-lg<?php if (empty($cart_items)) echo ' disabled'; ?>">Proceed to Checkout</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 