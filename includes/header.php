<?php
require_once __DIR__ . '/../config/database.php';

// Get all active categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current = basename($_SERVER['PHP_SELF']);
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare('SELECT SUM(quantity) as count FROM cart_items WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cart_count = $result['count'] ?? 0;
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">TriHealth Mart</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?php if($current=='index.php')echo ' active'; ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($current=='products.php')echo ' active'; ?>" href="products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($current=='categories.php')echo ' active'; ?>" href="categories.php">Categories</a>
                </li>
            </ul>
            <form class="d-flex me-3" method="get" action="products.php">
                <input class="form-control me-2" type="search" name="search" placeholder="Search products..." aria-label="Search" value="<?php echo $search_query; ?>">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="d-flex align-items-center">
                <a href="cart.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="btn btn-outline-primary me-2">Profile</a>
                    <a href="logout.php" class="btn btn-primary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<div style="height:70px;"></div> 