<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    
    if ($order_id) {
        if ($_POST['action'] === 'confirm') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);
            header('Location: orders.php?message=Order confirmed successfully');
            exit();
        } elseif ($_POST['action'] === 'complete') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$order_id]);
            header('Location: orders.php?message=Order completed successfully');
            exit();
        } elseif ($_POST['action'] === 'deliver') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
            $stmt->execute([$order_id]);
            header('Location: orders.php?message=Order delivered successfully');
            exit();
        } elseif ($_POST['action'] === 'cancel') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$order_id]);
            header('Location: orders.php?message=Order cancelled successfully');
            exit();
        }
    }
}

// Handle export to Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="orders_export.xls"');
    header('Cache-Control: max-age=0');
    
    // Get all orders
    $stmt = $conn->query("
        SELECT o.*, u.username, u.email, u.phone, u.address,
               GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Order ID\tCustomer\tEmail\tPhone\tAddress\tItems\tTotal\tStatus\tDate\n";
    foreach ($orders as $order) {
        echo implode("\t", [
            $order['id'],
            $order['username'],
            $order['email'],
            $order['phone'],
            $order['address'],
            $order['items'],
            $order['total_amount'],
            $order['status'],
            $order['created_at']
        ]) . "\n";
    }
    exit();
}

// Get all orders with user and delivery man info
$stmt = $conn->query('
    SELECT o.*, 
           u.username as customer_name,
           u.phone as customer_phone,
           dm.username as delivery_man_name,
           dm.phone as delivery_man_phone,
           COUNT(oi.id) as total_items,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN delivery_men dm ON o.deliveryman_id = dm.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
');
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total_orders' => $conn->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending_orders' => $conn->query('SELECT COUNT(*) FROM orders WHERE status = "pending"')->fetchColumn(),
    'total_revenue' => $conn->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered"')->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - TriHealth Mart</title>
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

        .orders-card {
            background: linear-gradient(45deg, var(--accent-color), #2980b9);
            color: white;
        }

        .pending-card {
            background: linear-gradient(45deg, var(--warning-color), #f39c12);
            color: white;
        }

        .revenue-card {
            background: linear-gradient(45deg, var(--success-color), #27ae60);
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-outfordelivery {
            background-color: #ffe0b2;
            color: #b26a00;
        }

        .status-delivered {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
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
                <a class="nav-link active" href="orders.php">
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
            <h4 class="mb-0">Orders Management</h4>
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
            <div class="col-md-6 col-lg-4">
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
            <div class="col-md-6 col-lg-4">
                <div class="card stats-card pending-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="card-title">Pending Orders</h5>
                        <h3 class="mb-0"><?php echo $stats['pending_orders']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
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
        </div>

        <!-- Orders Table -->
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">All Orders</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportOrders()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <button class="btn btn-outline-success" onclick="printOrders()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Delivery Man</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo $order['customer_phone']; ?></small>
                                </td>
                                <td>
                                    <div><?php echo $order['total_items']; ?> items</div>
                                    <small class="text-muted"><?php echo $order['total_quantity']; ?> units</small>
                                </td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $status_map = [
                                        'pending' => ['text' => 'Pending', 'class' => 'status-pending'],
                                        'confirmed' => ['text' => 'Confirmed', 'class' => 'status-confirmed'],
                                        'out_for_delivery' => ['text' => 'Out for Delivery', 'class' => 'status-outfordelivery'],
                                        'delivered' => ['text' => 'Delivered', 'class' => 'status-delivered'],
                                        'cancelled' => ['text' => 'Cancelled', 'class' => 'status-cancelled'],
                                    ];
                                    $s = strtolower(trim($order['status']));
                                    $badge = $status_map[$s] ?? ['text' => ucfirst($order['status']), 'class' => 'status-secondary'];
                                    ?>
                                    <span class="status-badge <?php echo $badge['class']; ?>">
                                        <?php echo $badge['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['delivery_man_name']): ?>
                                    <div><?php echo htmlspecialchars($order['delivery_man_name']); ?></div>
                                    <small class="text-muted"><?php echo $order['delivery_man_phone']; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($order['status'] === 'pending'): ?>
                                        <button class="btn btn-success btn-sm" onclick="quickConfirmOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($order['status'] === 'confirmed'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="quickOutForDeliveryOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-truck"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($order['status'] === 'out_for_delivery'): ?>
                                        <button class="btn btn-success btn-sm" onclick="quickDeliverOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-box"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                        <button class="btn btn-warning btn-sm" onclick="quickCancelOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($order['status'] !== 'delivered'): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

function viewOrder(id) {
    window.location.href = `view_order.php?id=${id}`;
}

function exportOrders() {
    // Implement export functionality
    console.log('Export orders');
}

function printOrders() {
    window.print();
}

function deleteOrder(id) {
    if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
        // Create form data
        const formData = new FormData();
        formData.append('order_id', id);

        // Send AJAX request
        fetch('delete_order.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Reload the page to update the table
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the order');
        });
    }
}

function updateOrderStatus(orderId, status, deliveryManId = null) {
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('status', status);
    if (deliveryManId) {
        formData.append('delivery_man_id', deliveryManId);
    }

    fetch('update_order_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Reload the page to update the table
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the order status');
    });
}

function quickConfirmOrder(orderId) {
    if (confirm('Confirm this order?')) {
        updateOrderStatus(orderId, 'confirmed');
    }
}

function quickOutForDeliveryOrder(orderId) {
    if (confirm('Mark this order as out for delivery?')) {
        updateOrderStatus(orderId, 'out_for_delivery');
    }
}

function quickDeliverOrder(orderId) {
    if (confirm('Mark this order as delivered?')) {
        updateOrderStatus(orderId, 'delivered');
    }
}

function quickCancelOrder(orderId) {
    if (confirm('Cancel this order?')) {
        updateOrderStatus(orderId, 'cancelled');
    }
}
</script>
</body>
</html> 