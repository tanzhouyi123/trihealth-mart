<?php
session_start();
require_once 'config/database.php';
$categories = $conn->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Categories</h2>
    <div class="row g-4">
        <?php foreach ($categories as $cat): ?>
        <div class="col-md-3">
            <div class="card h-100">
                <?php if (!empty($cat['image']) && file_exists($cat['image'])): ?>
                    <img src="<?php echo htmlspecialchars($cat['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="fas fa-image fa-3x text-muted"></i>
                    </div>
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($cat['name']); ?></h5>
                    <p class="card-text text-muted"><?php echo htmlspecialchars($cat['description']); ?></p>
                    <a href="products.php?category=<?php echo $cat['id']; ?>" class="btn btn-outline-primary w-100">View Products</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?>
        <div class="col-12 text-center text-muted">No categories found.</div>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 