<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.username as customer_name, u.email as customer_email, u.phone as customer_phone,
           dm.username as delivery_man_name, dm.phone as delivery_man_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN delivery_men dm ON o.deliveryman_id = dm.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $delivery_man_id = filter_input(INPUT_POST, 'delivery_man_id', FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, deliveryman_id = ? WHERE id = ?");
    
    if ($stmt->execute([$new_status, $delivery_man_id, $order_id])) {
        header("Location: view_order.php?id=" . $order_id . "&success=1");
        exit();
    } else {
        $error = "Failed to update order status";
    }
}

// Get available delivery men
$stmt = $conn->prepare("SELECT id, username, phone FROM delivery_men WHERE status = 'active'");
$stmt->execute();
$delivery_men = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - TriHealth Mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-color);
            color: var(--text-light);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .admin-sidebar.collapsed {
            width: 60px;
        }
        .admin-sidebar .nav-link {
            padding: 0.8rem 1rem;
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        .admin-sidebar .nav-link:hover {
            background: var(--secondary-color);
            color: var(--text-light);
        }
        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem 1rem 2rem 1rem;
            transition: all 0.3s ease;
        }
        .admin-sidebar.collapsed + .main-content {
            margin-left: 60px;
        }
        .order-status {
            font-size: 1rem;
            padding: 0.4rem 1.2rem;
            border-radius: 1.5rem;
            font-weight: 600;
            letter-spacing: 1px;
            display: inline-block;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d1ecf1; color: #0c5460; }
        .status-processing { background-color: #cce5ff; color: #004085; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-delivered { background-color: #d1e7dd; color: #0f5132; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        .card {
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 2rem;
        }
        .card-header {
            background: linear-gradient(90deg, #3498db 0%, #6dd5fa 100%);
            color: #fff;
            border-radius: 14px 14px 0 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .btn-back {
            background: var(--primary-color);
            color: #fff;
            border-radius: 20px;
            font-weight: 500;
            padding: 0.5rem 1.5rem;
            transition: background 0.2s;
        }
        .btn-back:hover {
            background: var(--accent-color);
            color: #fff;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="p-3">
            <h4 class="text-white mb-4">TriHealth Mart</h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="products.php">
                        <i class="fas fa-box me-2"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="categories.php">
                        <i class="fas fa-tags me-2"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="delivery_men.php">
                        <i class="fas fa-truck me-2"></i> Delivery Men
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="payment_methods.php">
                        <i class="fas fa-credit-card me-2"></i> Payment Methods
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title">Order Details</h2>
                <a href="orders.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Order status updated successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <div class="row g-4">
                <!-- Order Information -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i>Order Information
                        </div>
                        <div class="card-body">
                            <div class="mb-3"><span class="fw-bold">Order ID:</span> #<?php echo $order['id']; ?></div>
                            <div class="mb-3"><span class="fw-bold">Order Date:</span> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></div>
                            <div class="mb-3"><span class="fw-bold">Status:</span> 
                                <?php
                                $status_map = [
                                    'pending' => ['text' => 'Pending', 'class' => 'status-pending'],
                                    'confirmed' => ['text' => 'Confirmed', 'class' => 'status-confirmed'],
                                    'processing' => ['text' => 'Processing', 'class' => 'status-processing'],
                                    'completed' => ['text' => 'Completed', 'class' => 'status-completed'],
                                    'delivered' => ['text' => 'Delivered', 'class' => 'status-delivered'],
                                    'cancelled' => ['text' => 'Cancelled', 'class' => 'status-cancelled'],
                                ];
                                $s = strtolower($order['status']);
                                $badge = $status_map[$s] ?? ['text' => ucfirst($order['status']), 'class' => 'status-secondary'];
                                ?>
                                <span class="order-status <?php echo $badge['class']; ?>">
                                    <?php echo $badge['text']; ?>
                                </span>
                            </div>
                            <div class="mb-3"><span class="fw-bold">Total Amount:</span> <span class="text-success">RM <?php echo number_format($order['total_amount'], 2); ?></span></div>
                            <div class="mb-3"><span class="fw-bold">Payment Method:</span> <?php echo ucfirst($order['payment_method']); ?></div>
                            <?php if ($order['reference_code']): ?>
                            <div class="mb-3"><span class="fw-bold">Reference Code:</span> <span class="text-primary fw-bold"><?php echo htmlspecialchars($order['reference_code']); ?></span></div>
                            <?php endif; ?>
                            <div class="mb-3"><span class="fw-bold">Payment Status:</span> 
                                <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Customer Information -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </div>
                        <div class="card-body">
                            <div class="mb-3"><span class="fw-bold">Name:</span> <?php echo $order['customer_name']; ?></div>
                            <div class="mb-3"><span class="fw-bold">Email:</span> <?php echo $order['customer_email']; ?></div>
                            <div class="mb-3"><span class="fw-bold">Phone:</span> <?php echo $order['customer_phone']; ?></div>
                            <div class="mb-3"><span class="fw-bold">Delivery Address:</span> <?php echo $order['delivery_address']; ?></div>
                        </div>
                    </div>
                </div>
                <!-- Order Items -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list me-2"></i>Order Items
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $img = $item['product_image'] ?? '';
                                                    $img_path = '';
                                                    if ($img && file_exists(__DIR__ . '/../uploads/products/' . $img)) {
                                                        $img_path = '../uploads/products/' . $img;
                                                    } elseif ($img && file_exists(__DIR__ . '/../assets/images/products/' . $img)) {
                                                        $img_path = '../assets/images/products/' . $img;
                                                    }
                                                    ?>
                                                    <?php if ($img_path): ?>
                                                        <img src="<?php echo $img_path; ?>" alt="<?php echo $item['product_name']; ?>" class="product-image me-3">
                                                    <?php else: ?>
                                                        <img src="https://via.placeholder.com/60x60?text=No+Image" alt="No Image" class="product-image me-3">
                                                    <?php endif; ?>
                                                    <span><?php echo $item['product_name']; ?></span>
                                                </div>
                                            </td>
                                            <td>RM <?php echo number_format($item['price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Update Status -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-edit me-2"></i>Update Order Status
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Order Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Assign Delivery Man</label>
                                    <select name="delivery_man_id" class="form-select">
                                        <option value="">Select Delivery Man</option>
                                        <?php foreach ($delivery_men as $dm): ?>
                                        <option value="<?php echo $dm['id']; ?>" <?php echo $order['deliveryman_id'] == $dm['id'] ? 'selected' : ''; ?>>
                                            <?php echo $dm['username']; ?> (<?php echo $dm['phone']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_status" class="btn btn-primary btn-lg px-4">
                                        <i class="fas fa-save me-2"></i>Update Status
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'btn btn-light position-fixed';
            toggleBtn.style.cssText = 'top: 10px; left: 10px; z-index: 1001;';
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(toggleBtn);
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });
            // Handle mobile view
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html> 