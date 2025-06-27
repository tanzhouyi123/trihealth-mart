<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's orders
$stmt = $conn->prepare('
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           GROUP_CONCAT(p.name SEPARATOR ", ") as product_names
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
');
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's reviews
$stmt = $conn->prepare('
    SELECT r.*, p.name as product_name, p.image as product_image
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
');
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);

        if (empty($email) || empty($phone) || empty($address)) {
            $error = 'All fields are required';
        } else {
            $stmt = $conn->prepare('UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?');
            if ($stmt->execute([$email, $phone, $address, $user_id])) {
                $success = 'Profile updated successfully';
                // Refresh user data
                $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update profile';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success = 'Password changed successfully';
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mb-3">
                            <i class="fas fa-user fa-3x"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                        <p class="text-muted">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                            <i class="fas fa-user me-2"></i> Profile Information
                        </a>
                        <a href="#orders" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="fas fa-shopping-bag me-2"></i> Order History
                        </a>
                        <a href="#reviews" class="list-group-item list-group-item-action" data-bs-toggle="list">
                            <i class="fas fa-star me-2"></i> My Reviews
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-8">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="tab-content">
                <!-- Profile Information Tab -->
                <div class="tab-pane fade show active" id="profile">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Profile Information</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>

                            <hr class="my-4">

                            <h5 class="card-title mb-4">Change Password</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Order History Tab -->
                <div class="tab-pane fade" id="orders">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Order History</h5>
                            <?php if (empty($orders)): ?>
                                <div class="alert alert-info">You haven't placed any orders yet.</div>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="card-title mb-0">Order #<?php echo $order['id']; ?></h6>
                                                <span class="badge
                                                    <?php
                                                    $status_map = [
                                                        'pending' => 'warning',
                                                        'confirmed' => 'info',
                                                        'out_for_delivery' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger',
                                                    ];
                                                    $s = strtolower(trim($order['status']));
                                                    echo 'bg-' . ($status_map[$s] ?? 'secondary');
                                                    ?>
                                                ">
                                                    <?php
                                                    $status_text = [
                                                        'pending' => 'Pending',
                                                        'confirmed' => 'Confirmed',
                                                        'out_for_delivery' => 'Out for Delivery',
                                                        'delivered' => 'Delivered',
                                                        'cancelled' => 'Cancelled',
                                                    ];
                                                    echo $status_text[$s] ?? ucfirst($order['status']);
                                                    ?>
                                                </span>
                                            </div>
                                            <p class="card-text">
                                                <small class="text-muted">Placed on: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></small>
                                            </p>
                                            <p class="card-text">
                                                <strong>Items:</strong> <?php echo htmlspecialchars($order['product_names']); ?>
                                            </p>
                                            <p class="card-text">
                                                <strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?>
                                            </p>
                                            <p class="card-text">
                                                <strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?>
                                            </p>
                                            <p class="card-text">
                                                <strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?>
                                            </p>
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <a href="review_order.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">Write a Review</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">My Reviews</h5>
                            <?php if (empty($reviews)): ?>
                                <div class="alert alert-info">You haven't written any reviews yet.</div>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <?php 
                                                $image_path = '';
                                                if ($review['product_image']) {
                                                    if (strpos($review['product_image'], 'uploads/') === 0) {
                                                        $image_path = $review['product_image'];
                                                    } elseif (strpos($review['product_image'], 'assets/') === 0) {
                                                        $image_path = $review['product_image'];
                                                    } else {
                                                        $image_path = "assets/images/products/" . $review['product_image'];
                                                    }
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($image_path); ?>" class="me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            <small class="text-muted">Posted on <?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 保持 tab 激活状态
const hash = window.location.hash;
if (hash) {
    const triggerEl = document.querySelector(`.list-group a[href='${hash}']`);
    if (triggerEl) {
        // 激活左侧菜单
        document.querySelectorAll('.list-group a').forEach(a => a.classList.remove('active'));
        triggerEl.classList.add('active');
        // 激活 tab 内容
        document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('show', 'active'));
        const tabContent = document.querySelector(hash);
        if (tabContent) {
            tabContent.classList.add('show', 'active');
        }
    }
}
// 切换 tab 时更新 hash
const tabLinks = document.querySelectorAll('.list-group a[data-bs-toggle="list"]');
tabLinks.forEach(link => {
    link.addEventListener('click', function(e) {
        window.location.hash = this.getAttribute('href');
    });
});
</script>
<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.avatar-circle i {
    color: #6c757d;
}
.list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>
</body>
</html> 