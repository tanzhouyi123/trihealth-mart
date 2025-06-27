<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
$stats = [
    'total_orders' => $conn->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending_orders' => $conn->query('SELECT COUNT(*) FROM orders WHERE status = "pending"')->fetchColumn(),
    'total_users' => $conn->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn(),
    'total_products' => $conn->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'total_delivery_men' => $conn->query('SELECT COUNT(*) FROM delivery_men')->fetchColumn(),
    'pending_delivery_men' => $conn->query('SELECT COUNT(*) FROM delivery_men WHERE status = "pending"')->fetchColumn(),
    'total_revenue' => $conn->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered"')->fetchColumn()
];

// Get recent orders
$recent_orders = $conn->query('
    SELECT o.*, u.username as customer_name
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);

// Get pending delivery men
$pending_delivery_men = $conn->query('
    SELECT * FROM delivery_men
    WHERE status = "pending"
    ORDER BY created_at DESC
    LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TriHealth Mart</title>
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

        .revenue-card {
            background: linear-gradient(45deg, var(--success-color), #27ae60);
            color: white;
        }

        .orders-card {
            background: linear-gradient(45deg, var(--accent-color), #2980b9);
            color: white;
        }

        .users-card {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
        }

        .products-card {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
            color: white;
        }

        .delivery-card {
            background: linear-gradient(45deg, var(--warning-color), #f39c12);
            color: white;
        }

        .pending-card {
            background: linear-gradient(45deg, #e67e22, #d35400);
            color: white;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <?php if ($stats['pending_orders'] > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                    <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    </a>
            </li>
            <li class="nav-item">
                    <a class="nav-link" href="categories.php">
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
                    <?php if ($stats['pending_delivery_men'] > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $stats['pending_delivery_men']; ?></span>
                    <?php endif; ?>
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
            <h4 class="mb-0">Dashboard</h4>
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
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card revenue-card">
                            <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h5 class="card-title">Total Revenue</h5>
                        <h3 class="mb-0">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card orders-card">
                            <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h5 class="card-title">Total Orders</h5>
                        <h3 class="mb-0"><?php echo $stats['total_orders']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stats-card users-card">
                            <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Total Users</h5>
                        <h3 class="mb-0"><?php echo $stats['total_users']; ?></h3>
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
                </div>

        <!-- Recent Orders and Pending Delivery Men -->
        <div class="row g-4">
            <div class="col-lg-8">
                        <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Recent Orders</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                            <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                echo match($order['status']) {
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'shipped' => 'primary',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>"><?php echo ucfirst($order['status']); ?></span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
            <div class="col-lg-4">
                        <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Pending Delivery Men</h5>
                            </div>
                            <div class="card-body">
                        <?php if (empty($pending_delivery_men)): ?>
                            <p class="text-muted mb-0">No pending delivery men applications.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pending_delivery_men as $delivery_man): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($delivery_man['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($delivery_man['phone']); ?></small>
                                        </div>
                                        <a href="delivery_men.php?id=<?php echo $delivery_man['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
});
</script>
</body>
</html> 