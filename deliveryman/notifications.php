<?php include 'header.php'; ?>
<?php
session_start();
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
$deliveryman = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'username' => 'Unknown',
    'id' => $_SESSION['user_id']
];

// Get notifications
$stmt = $conn->prepare('
    SELECT * FROM notifications 
    WHERE user_id = ? AND user_type = "deliveryman"
    ORDER BY created_at DESC
');
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Mark notifications as read
if (isset($_POST['mark_read'])) {
    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_SANITIZE_NUMBER_INT);
    if ($notification_id) {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
    }
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = "deliveryman"');
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: notifications.php');
    exit();
}

// Delete notification
if (isset($_POST['delete_notification'])) {
    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_SANITIZE_NUMBER_INT);
    if ($notification_id) {
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
    }
}

// Delete all notifications
if (isset($_POST['delete_all'])) {
    $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ? AND user_type = "deliveryman"');
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: notifications.php');
    exit();
}

// Get unread notification count
$stmt = $conn->prepare('
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = ? AND user_type = "deliveryman" AND is_read = 0
');
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TriHealth Mart</title>
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
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left-color: #2c3e50;
            background-color: #f8f9fa;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #e9ecef;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
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
                    <a class="nav-link active" href="notifications.php">
                        <i class="fas fa-bell"></i>
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
        <a class="nav-link" href="dashboard.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h4>
            <div class="btn-group">
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                        <i class="fas fa-check-double me-2"></i>Mark All as Read
                    </button>
                </form>
                <form method="POST" class="d-inline ms-2">
                    <button type="submit" name="delete_all" class="btn btn-outline-danger" 
                            onclick="return confirm('Are you sure you want to delete all notifications?')">
                        <i class="fas fa-trash me-2"></i>Delete All
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No notifications yet</h5>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="notification-icon me-3">
                                        <?php
                                        $icon = match($notification['type']) {
                                            'order' => 'fa-shopping-cart',
                                            'system' => 'fa-cog',
                                            'alert' => 'fa-exclamation-triangle',
                                            default => 'fa-bell'
                                        };
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <div class="d-flex align-items-center">
                                                <small class="notification-time me-3">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                                <div class="dropdown">
                                                    <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <?php if (!$notification['is_read']): ?>
                                                            <li>
                                                                <form method="POST">
                                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                    <button type="submit" name="mark_read" class="dropdown-item">
                                                                        <i class="fas fa-check me-2"></i>Mark as Read
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="POST">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" name="delete_notification" class="dropdown-item text-danger"
                                                                        onclick="return confirm('Are you sure you want to delete this notification?')">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
</script>
</body>
</html> 