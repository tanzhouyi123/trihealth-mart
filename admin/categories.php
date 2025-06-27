<?php
session_start();
require_once '../config/database.php';
require_once '../includes/image_helper.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$message = '';
$error = '';

// Handle category status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    
    if ($category_id) {
        if ($_POST['action'] === 'update') {
            $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
            $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            
            // Validate inputs
            if (empty($name)) {
                $error = "Category name is required";
            } elseif (strlen($name) > 100) {
                $error = "Category name must be less than 100 characters";
            } elseif (!in_array($status, ['active', 'inactive'])) {
                $error = "Invalid status value";
            } else {
                // Handle image upload
                $image_path = $_POST['current_image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    if (!in_array($_FILES['image']['type'], $allowed_types)) {
                        $error = "Invalid image type. Only JPG, PNG, GIF and WebP are allowed.";
                    } elseif ($_FILES['image']['size'] > $max_size) {
                        $error = "Image size must be less than 5MB";
                    } else {
                        $upload_dir = '../uploads/categories/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                        $target_path = $upload_dir . $file_name;

                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            // Delete old image if exists and different
                            if ($_POST['current_image'] && file_exists('../' . $_POST['current_image'])) {
                                unlink('../' . $_POST['current_image']);
                            }
                            $image_path = 'uploads/categories/' . $file_name;
                        } else {
                            $error = "Failed to upload image. Please try again.";
                        }
                    }
                }

                if (empty($error)) {
                    try {
                        // Check if name already exists for other categories
                        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                        $stmt->execute([$name, $category_id]);
                        if ($stmt->fetch()) {
                            $error = "A category with this name already exists";
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE categories 
                                SET name = ?, description = ?, image = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE id = ?
                            ");
                            
                            if ($stmt->execute([$name, $description, $image_path, $status, $category_id])) {
                                $message = "Category updated successfully";
                            } else {
                                $error = "Failed to update category";
                            }
                        }
                    } catch (PDOException $e) {
                        $error = "Database error: " . $e->getMessage();
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            try {
                // First check if category has any products
                $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $product_count = $stmt->fetchColumn();

                if ($product_count > 0) {
                    $error = "Cannot delete category because it contains {$product_count} products. Please remove or reassign the products first.";
                } else {
                    // Get the category to check if it has an image
                    $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Delete the category
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    if ($stmt->execute([$category_id])) {
                        // Delete the image file if it exists
                        if ($category['image']) {
                            $image_path = '../' . $category['image'];
                            if (file_exists($image_path)) {
                                if (!unlink($image_path)) {
                                    $message = "Category deleted successfully but failed to remove image file.";
                                } else {
                                    $message = "Category deleted successfully";
                                }
                            } else {
                                $message = "Category deleted successfully";
                            }
                        } else {
                            $message = "Category deleted successfully";
                        }
                        header('Location: categories.php?message=' . urlencode($message));
                        exit();
                    } else {
                        $error = "Failed to delete category";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            try {
                $stmt = $conn->prepare("SELECT status FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $current_status = $stmt->fetchColumn();
                
                $new_status = ($current_status === 'active') ? 'inactive' : 'active';
                
                $stmt = $conn->prepare("UPDATE categories SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$new_status, $category_id])) {
                    $message = "Category status updated successfully";
                } else {
                    $error = "Failed to update category status";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Invalid category ID";
    }
}

// Get message from URL parameter
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Get all categories with product count and better ordering
try {
    $stmt = $conn->query('
        SELECT c.*, 
               COUNT(p.id) as product_count,
               COALESCE(SUM(p.stock), 0) as total_stock
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = "active"
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load categories: " . $e->getMessage();
    $categories = [];
}

// Get statistics
try {
    $stats = [
        'total_categories' => $conn->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'active_categories' => $conn->query('SELECT COUNT(*) FROM categories WHERE status = "active"')->fetchColumn(),
        'total_products' => $conn->query('SELECT COUNT(*) FROM products WHERE status = "active"')->fetchColumn(),
        'categories_with_products' => $conn->query('SELECT COUNT(DISTINCT category_id) FROM products WHERE status = "active"')->fetchColumn()
    ];
} catch (PDOException $e) {
    $stats = [
        'total_categories' => 0,
        'active_categories' => 0,
        'total_products' => 0,
        'categories_with_products' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 280px;
            --header-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-light: #ecf0f1;
            --text-dark: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-color);
            color: var(--text-light);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .admin-sidebar.collapsed {
            left: calc(-1 * var(--sidebar-width));
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand {
            color: var(--text-light);
            font-size: 1.25rem;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: var(--text-light);
            background: rgba(255,255,255,0.1);
        }

        .nav-link.active {
            color: var(--text-light);
            background: var(--accent-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
            padding: 1rem;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Header Styles */
        .admin-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .sidebar-toggle:hover {
            background: #f8f9fa;
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .user-dropdown:hover {
            background: #f8f9fa;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Card Styles */
        .stats-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .categories-card {
            background: linear-gradient(45deg, var(--accent-color), #2980b9);
            color: white;
        }

        .active-card {
            background: linear-gradient(45deg, var(--success-color), #27ae60);
            color: white;
        }

        .products-card {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
        }

        .with-products-card {
            background: linear-gradient(45deg, #e67e22, #d35400);
            color: white;
        }

        /* Table Styles */
        .table-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .table-card .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.5rem;
        }

        .table-card .table {
            margin-bottom: 0;
        }

        .table-card .table th {
            border-top: none;
            font-weight: 600;
            color: var(--text-dark);
        }

        .table-card .table td {
            vertical-align: middle;
        }

        .category-icon {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #eee;
        }

        .category-icon-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #eee;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 10px;
        }

        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1.5rem;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #eee;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-sidebar {
                left: calc(-1 * var(--sidebar-width));
            }
            
            .admin-sidebar.show {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .table-responsive {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <i class="fas fa-store"></i>
            <span>TriHealth Mart</span>
        </a>
    </div>
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payment_methods.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="delivery_men.php">
                    <i class="fas fa-motorcycle"></i>
                    <span>Delivery Men</span>
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Header -->
    <div class="admin-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h4 class="mb-0">Categories Management</h4>
        </div>
        <div class="user-dropdown" data-bs-toggle="dropdown">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <i class="fas fa-chevron-down ms-2"></i>
        </div>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Dashboard Content -->
    <div class="container-fluid">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card categories-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h5 class="card-title">Total Categories</h5>
                        <h3 class="mb-0"><?php echo $stats['total_categories']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card active-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h5 class="card-title">Active Categories</h5>
                        <h3 class="mb-0"><?php echo $stats['active_categories']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card products-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <h5 class="card-title">Total Products</h5>
                        <h3 class="mb-0"><?php echo $stats['total_products']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card with-products-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h5 class="card-title">Categories with Products</h5>
                        <h3 class="mb-0"><?php echo $stats['categories_with_products']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tags me-2"></i>All Categories
                </h5>
                <a href="add_category.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Category
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No categories found</h5>
                        <p class="text-muted">Start by adding your first category</p>
                        <a href="add_category.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Category
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Products</th>
                                    <th>Total Stock</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <?php if ($category['image']): ?>
                                            <img src="../<?php echo getImagePath($category['image'], 'categories'); ?>" 
                                                 alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                 class="category-icon"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="category-icon-placeholder" style="display: none;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="category-icon-placeholder">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($category['description']): ?>
                                            <span class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 50)); ?><?php echo strlen($category['description']) > 50 ? '...' : ''; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $category['status']; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $category['product_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo number_format($category['total_stock']); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $category['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn-<?php echo $category['status'] === 'active' ? 'warning' : 'success'; ?> btn-sm" 
                                                        title="<?php echo $category['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $category['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit and Delete Modals -->
<?php foreach ($categories as $category): ?>
<!-- Edit Modal -->
<div class="modal fade" id="editCategoryModal<?php echo $category['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($category['image']); ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                <div class="form-text">Maximum 100 characters</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" 
                                          placeholder="Enter category description..."><?php echo htmlspecialchars($category['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category Image</label>
                                <?php if ($category['image']): ?>
                                    <div class="mb-3">
                                        <img src="../<?php echo getImagePath($category['image'], 'categories'); ?>" 
                                             alt="Current Image" class="preview-image w-100">
                                        <small class="text-muted d-block mt-1">Current image</small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="image" accept="image/*" onchange="previewImage(this, 'preview<?php echo $category['id']; ?>')">
                                <div class="form-text">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        Leave empty to keep current image<br>
                                        Supported: JPG, PNG, GIF, WebP (max 5MB)
                                    </small>
                                </div>
                                <div id="preview<?php echo $category['id']; ?>" class="mt-2" style="display: none;">
                                    <img src="" alt="Preview" class="preview-image w-100">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Info -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Products:</strong> <?php echo $category['product_count']; ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Total Stock:</strong> <?php echo number_format($category['total_stock']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Created:</strong> <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Updated:</strong> <?php echo date('M j, Y', strtotime($category['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteCategoryModal<?php echo $category['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                    <h5>Are you sure you want to delete this category?</h5>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-info-circle me-2"></i>Category Information</h6>
                    <ul class="mb-0">
                        <li><strong>Name:</strong> <?php echo htmlspecialchars($category['name']); ?></li>
                        <li><strong>Products:</strong> <?php echo $category['product_count']; ?> items</li>
                        <li><strong>Total Stock:</strong> <?php echo number_format($category['total_stock']); ?> units</li>
                    </ul>
                </div>

                <?php if ($category['product_count'] > 0): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Cannot Delete</h6>
                        <p class="mb-0">
                            This category contains <strong><?php echo $category['product_count']; ?> products</strong>. 
                            You must remove or reassign these products before deleting the category.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" <?php echo $category['product_count'] > 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-trash me-2"></i>Delete Category
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });

    // Handle responsive behavior
    function handleResize() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    }

    window.addEventListener('resize', handleResize);
    handleResize();

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Image preview function
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const previewImg = preview.querySelector('img');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

// Confirm delete function
function confirmDelete(categoryId, categoryName) {
    if (confirm(`Are you sure you want to delete the category "${categoryName}"?`)) {
        document.getElementById('deleteForm' + categoryId).submit();
    }
}
</script>
</body>
</html> 