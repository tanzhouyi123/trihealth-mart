<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify order exists and belongs to user
$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = "delivered"');
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: profile.php');
    exit();
}

// Get order items
$stmt = $conn->prepare('
    SELECT oi.*, p.name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
');
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    // Validate input
    if ($rating < 1 || $rating > 5) {
        $error = 'Rating must be between 1 and 5';
    } else {
        // Check if review already exists
        $stmt = $conn->prepare('SELECT id FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?');
        $stmt->execute([$user_id, $product_id, $order_id]);
        if ($stmt->fetch()) {
            $error = 'You have already reviewed this product for this order';
        } else {
            // Add review
            $stmt = $conn->prepare('INSERT INTO reviews (user_id, product_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
            if ($stmt->execute([$user_id, $product_id, $order_id, $rating, $comment])) {
                $success = 'Review submitted successfully';
            } else {
                $error = 'Failed to submit review';
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
    <title>Review Order - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Review Order #<?php echo $order_id; ?></h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <div class="row">
        <?php foreach ($items as $item): ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="assets/images/products/<?php echo $item['image']; ?>" class="me-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                <p class="text-muted mb-0">Quantity: <?php echo $item['quantity']; ?></p>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?><?php echo $item['product_id']; ?>" required>
                                        <label for="rating<?php echo $i; ?><?php echo $item['product_id']; ?>">☆</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="comment<?php echo $item['product_id']; ?>" class="form-label">Comment</label>
                                <textarea class="form-control" id="comment<?php echo $item['product_id']; ?>" name="comment" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.rating input {
    display: none;
}
.rating label {
    cursor: pointer;
    font-size: 30px;
    color: #ddd;
    padding: 5px;
}
.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #ffd700;
}
</style>
</body>
</html> 