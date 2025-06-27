<?php
session_start();
require_once 'config/database.php';

// 获取所有分类
$categories = $conn->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// 分类筛选
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build the query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($category_id) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $query .= " ORDER BY p.name ASC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        <div class="col-md-6">
            <div class="d-flex justify-content-end">
                <select name="sort" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="newest" <?php if($sort=='newest')echo 'selected'; ?>>Newest</option>
                    <option value="price_low" <?php if($sort=='price_low')echo 'selected'; ?>>Price: Low to High</option>
                    <option value="price_high" <?php if($sort=='price_high')echo 'selected'; ?>>Price: High to Low</option>
                    <option value="name" <?php if($sort=='name')echo 'selected'; ?>>Name: A to Z</option>
                </select>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group">
                <a href="products.php" class="btn btn-outline-primary <?php if(!$category_id)echo 'active'; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                <a href="products.php?category=<?php echo $cat['id']; ?>" class="btn btn-outline-primary <?php if($category_id==$cat['id'])echo 'active'; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
        <div class="col-md-3">
            <div class="card h-100">
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
                <img src="<?php echo htmlspecialchars($image_path); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                    <p class="card-text text-muted"><?php echo htmlspecialchars($product['description']); ?></p>
                    <p class="card-text fw-bold">$<?php echo number_format($product['price'], 2); ?></p>
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">View Details</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <div class="col-12 text-center text-muted">No products found.</div>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 