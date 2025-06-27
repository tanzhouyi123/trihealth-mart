<?php
session_start();
require_once 'config/database.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: products.php');
    exit();
}
$id = intval($_GET['id']);
$stmt = $conn->prepare('SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: products.php');
    exit();
}

// Get reviews
$stmt = $conn->prepare('
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
');
$stmt->execute([$id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$stmt = $conn->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ?');
$stmt->execute([$id]);
$rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = round($rating_stats['avg_rating'], 1);
$review_count = $rating_stats['review_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <div class="row">
        <div class="col-md-5">
            <?php 
            $image_path = '';
            if ($product['image']) {
                if (strpos($product['image'], 'uploads/') === 0) {
                    $image_path = $product['image'];
                } elseif (strpos($product['image'], 'assets/') === 0) {
                    $image_path = $product['image'];
                } else {
                    $image_path = "assets/images/products/" . $product['image'];
                }
            }
            ?>
            <img src="<?php echo htmlspecialchars($image_path); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>
        <div class="col-md-7">
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <p class="text-muted mb-1">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
            <?php if ($review_count > 0): ?>
            <div class="mb-2">
                <div class="d-flex align-items-center">
                    <div class="text-warning me-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-muted">(<?php echo $review_count; ?> reviews)</span>
                </div>
            </div>
            <?php endif; ?>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <h4 class="fw-bold mb-3">$<?php echo number_format($product['price'], 2); ?></h4>
            <p>Stock: <span class="fw-bold <?php echo $product['stock'] < 10 ? 'text-danger' : 'text-success'; ?>"><?php echo $product['stock']; ?></span></p>
            <form method="POST" action="add_to_cart.php" class="mb-3">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <div class="input-group mb-3" style="max-width:200px;">
                    <input type="number" name="quantity" class="form-control" min="1" max="<?php echo $product['stock']; ?>" value="1" required>
                    <button type="submit" class="btn btn-primary">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="mt-5">
        <h3>Customer Reviews</h3>
        <?php if (empty($reviews)): ?>
            <p class="text-muted">No reviews yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($review['username']); ?></h6>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <div class="text-warning">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 