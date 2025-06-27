<?php 
include 'header.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a deliveryman
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'deliveryman') {
    header('Location: ../login.php');
    exit();
}

// Get deliveryman details
$stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$deliveryman = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize default values if deliveryman not found
$is_available = false;
$working_hours_start = '09:00:00';
$working_hours_end = '17:00:00';
$max_delivery_distance = 10;

if ($deliveryman) {
    $is_available = (bool)$deliveryman['is_available'];
    $working_hours_start = $deliveryman['working_hours_start'] ?? '09:00:00';
    $working_hours_end = $deliveryman['working_hours_end'] ?? '17:00:00';
    $max_delivery_distance = $deliveryman['max_delivery_distance'] ?? 10;
}

// Get delivery statistics
$stmt = $conn->prepare('
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = "out_for_delivery" THEN 1 ELSE 0 END) as active_orders,
        SUM(CASE WHEN status = "pending" OR status = "confirmed" THEN 1 ELSE 0 END) as pending_orders
    FROM orders 
    WHERE deliveryman_id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_orders' => 0,
    'completed_orders' => 0,
    'active_orders' => 0,
    'pending_orders' => 0
];

// Get recent orders
$stmt = $conn->prepare('
    SELECT o.*, u.username as customer_name, u.phone as customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.deliveryman_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
');
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get earnings for current month
$stmt = $conn->prepare('
    SELECT COALESCE(SUM(total_amount), 0) as monthly_earnings
    FROM orders
    WHERE deliveryman_id = ? 
    AND status = "delivered"
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
');
$stmt->execute([$_SESSION['user_id']]);
$monthly_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_earnings'] ?? 0;

// Get delivery performance
$stmt = $conn->prepare('
    SELECT 
        AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_delivery_time
    FROM orders
    WHERE deliveryman_id = ? 
    AND status = "delivered"
    AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
');
$stmt->execute([$_SESSION['user_id']]);
$avg_delivery_time = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_delivery_time'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: #2c3e50;
            padding: 1rem;
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 999;
            transition: all 0.3s;
        }
        .navbar.expanded {
            left: 0;
        }
        .navbar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.5rem 1rem;
        }
        .navbar-nav .nav-link:hover {
            color: white;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #2c3e50;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar.collapsed {
            left: -250px;
        }
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.8rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .toggle-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 4px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-left: 4px solid #2c3e50;
        }
        .stat-card .icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(44, 62, 80, 0.1);
        }
        .stat-card .icon i {
            font-size: 24px;
            color: #2c3e50;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
<button class="toggle-btn" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg" id="deliverymanNavbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">TriHealth Mart Delivery</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($deliveryman['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="sidebar" id="sidebar">
    <div class="p-3">
        <h4 class="text-white">Deliveryman Panel</h4>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a class="nav-link" href="orders.php">
            <i class="fas fa-shopping-cart me-2"></i> My Orders
        </a>
        <a class="nav-link" href="profile.php">
            <i class="fas fa-user me-2"></i> Profile
        </a>
        <a class="nav-link" href="settings.php">
            <i class="fas fa-cog me-2"></i> Settings
        </a>
        <a class="nav-link" href="../logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </nav>
</div>

<div class="main-content p-4" id="mainContent">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Orders</h6>
                                <h3 class="mb-0"><?php echo $stats['total_orders']; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Completed Orders</h6>
                                <h3 class="mb-0"><?php echo $stats['completed_orders']; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Active Orders</h6>
                                <h3 class="mb-0"><?php echo $stats['active_orders']; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="fas fa-truck"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Monthly Earnings</h6>
                                <h3 class="mb-0">৳<?php echo number_format($monthly_earnings, 2); ?></h3>
                            </div>
                            <div class="icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Recent Orders</h5>
                        <a href="orders.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No orders yet</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                                </td>
                                                <td>৳<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($order['status']) {
                                                            'pending' => 'warning',
                                                            'confirmed' => 'info',
                                                            'out_for_delivery' => 'primary',
                                                            'delivered' => 'success',
                                                            'cancelled' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Average Delivery Time</h6>
                            <h3 class="mb-0"><?php echo $avg_delivery_time; ?> minutes</h3>
                        </div>
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Delivery Success Rate</h6>
                            <div class="progress">
                                <?php 
                                $success_rate = $stats['total_orders'] > 0 
                                    ? round(($stats['completed_orders'] / $stats['total_orders']) * 100) 
                                    : 0;
                                ?>
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $success_rate; ?>%">
                                    <?php echo $success_rate; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h2 class="text-xl font-semibold mb-4">Availability Status</h2>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-gray-600">Current Status:</span>
                                    <span class="ml-2 font-medium <?php echo $is_available ? '' : 'text-danger'; ?>" <?php if ($is_available) echo 'style="color: #06b6d4;"'; ?>>
                                        <?php echo $is_available ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </div>
                                <button id="toggleAvailability"
                                    class="btn <?php echo $is_available ? 'btn-info' : 'btn-danger'; ?>">
                                    <?php echo $is_available ? 'Set Unavailable' : 'Set Available'; ?>
                                </button>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h2 class="text-xl font-semibold mb-4">Working Hours</h2>
                            <div class="flex items-center space-x-4">
                                <div>
                                    <span class="text-gray-600">Start Time:</span>
                                    <span class="ml-2 font-medium"><?php echo date('h:i A', strtotime($working_hours_start)); ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">End Time:</span>
                                    <span class="ml-2 font-medium"><?php echo date('h:i A', strtotime($working_hours_end)); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 mb-6">
                            <h2 class="text-xl font-semibold mb-4">Max Delivery Distance</h2>
                            <div class="flex items-center">
                                <span class="text-gray-600">Maximum distance:</span>
                                <span class="ml-2 font-medium"><?php echo $max_delivery_distance; ?> km</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const navbar = document.getElementById('deliverymanNavbar');
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');
    navbar.classList.toggle('expanded');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const navbar = document.getElementById('deliverymanNavbar');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !sidebarToggle.contains(event.target) && 
        !sidebar.classList.contains('collapsed')) {
        sidebar.classList.add('collapsed');
        document.getElementById('mainContent').classList.add('expanded');
        navbar.classList.add('expanded');
    }
});

// Handle availability toggle
document.getElementById('toggleAvailability').addEventListener('click', function() {
    const isAvailable = this.classList.contains('btn-info');
    fetch('update_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'is_available=' + (isAvailable ? 0 : 1)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            this.classList.toggle('btn-info');
            this.classList.toggle('btn-danger');
            this.textContent = isAvailable ? 'Set Available' : 'Set Unavailable';
            // Update the status text color
            const statusText = this.previousElementSibling.querySelector('span:last-child');
            if (isAvailable) {
                statusText.classList.add('text-danger');
                statusText.removeAttribute('style');
                statusText.textContent = 'Unavailable';
            } else {
                statusText.classList.remove('text-danger');
                statusText.setAttribute('style', 'color: #06b6d4;');
                statusText.textContent = 'Available';
            }
        } else {
            alert('Failed to update availability status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update availability status');
    });
});
</script>
</body>
</html> 