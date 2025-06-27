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
    'email_notifications' => 0,
    'sms_notifications' => 0,
    'push_notifications' => 0,
    'show_phone' => 0,
    'show_email' => 0,
    'show_address' => 0,
    'is_available' => 0,
    'working_hours_start' => '09:00:00',
    'working_hours_end' => '17:00:00',
    'max_delivery_distance' => 10
];

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;

        $stmt = $conn->prepare('
            UPDATE delivery_men 
            SET email_notifications = ?, sms_notifications = ?, push_notifications = ?
            WHERE id = ?
        ');
        if ($stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $_SESSION['user_id']])) {
            $success = 'Notification settings updated successfully';
            // Refresh deliveryman data
            $stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $deliveryman = $stmt->fetch(PDO::FETCH_ASSOC) ?: $deliveryman;
        } else {
            $error = 'Failed to update notification settings';
        }
    } elseif (isset($_POST['update_privacy'])) {
        $show_phone = isset($_POST['show_phone']) ? 1 : 0;
        $show_email = isset($_POST['show_email']) ? 1 : 0;
        $show_address = isset($_POST['show_address']) ? 1 : 0;

        $stmt = $conn->prepare('
            UPDATE delivery_men 
            SET show_phone = ?, show_email = ?, show_address = ?
            WHERE id = ?
        ');
        if ($stmt->execute([$show_phone, $show_email, $show_address, $_SESSION['user_id']])) {
            $success = 'Privacy settings updated successfully';
            // Refresh deliveryman data
            $stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $deliveryman = $stmt->fetch(PDO::FETCH_ASSOC) ?: $deliveryman;
        } else {
            $error = 'Failed to update privacy settings';
        }
    } elseif (isset($_POST['update_availability'])) {
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $working_hours_start = $_POST['working_hours_start'] ?? '09:00:00';
        $working_hours_end = $_POST['working_hours_end'] ?? '17:00:00';
        $max_delivery_distance = filter_input(INPUT_POST, 'max_delivery_distance', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 10;

        if (empty($working_hours_start) || empty($working_hours_end) || empty($max_delivery_distance)) {
            $error = 'All fields are required';
        } else {
            $stmt = $conn->prepare('
                UPDATE delivery_men 
                SET is_available = ?, working_hours_start = ?, working_hours_end = ?, max_delivery_distance = ?
                WHERE id = ?
            ');
            if ($stmt->execute([$is_available, $working_hours_start, $working_hours_end, $max_delivery_distance, $_SESSION['user_id']])) {
                $success = 'Availability settings updated successfully';
                // Refresh deliveryman data
                $stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $deliveryman = $stmt->fetch(PDO::FETCH_ASSOC) ?: $deliveryman;
            } else {
                $error = 'Failed to update availability settings';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TriHealth Mart</title>
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
        .form-check-input:checked {
            background-color: #2c3e50;
            border-color: #2c3e50;
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
        <a class="nav-link active" href="settings.php">
            <i class="fas fa-cog me-2"></i> Settings
        </a>
        <a class="nav-link" href="../logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </nav>
</div>

<div class="main-content p-4" id="mainContent">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications"
                                           <?php echo $deliveryman['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications"
                                           <?php echo $deliveryman['sms_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications"
                                           <?php echo $deliveryman['push_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="push_notifications">Push Notifications</label>
                                </div>
                            </div>
                            <button type="submit" name="update_notifications" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_phone" name="show_phone"
                                           <?php echo $deliveryman['show_phone'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_phone">Show Phone Number</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_email" name="show_email"
                                           <?php echo $deliveryman['show_email'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_email">Show Email Address</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_address" name="show_address"
                                           <?php echo $deliveryman['show_address'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_address">Show Address</label>
                                </div>
                            </div>
                            <button type="submit" name="update_privacy" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Availability Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_available" name="is_available"
                                               <?php echo $deliveryman['is_available'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_available">Available for Delivery</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_delivery_distance" class="form-label">Maximum Delivery Distance (km)</label>
                                    <input type="number" class="form-control" id="max_delivery_distance" name="max_delivery_distance"
                                           value="<?php echo htmlspecialchars($deliveryman['max_delivery_distance']); ?>" step="0.1" min="0" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="working_hours_start" class="form-label">Working Hours Start</label>
                                    <input type="time" class="form-control" id="working_hours_start" name="working_hours_start"
                                           value="<?php echo htmlspecialchars($deliveryman['working_hours_start']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_hours_end" class="form-label">Working Hours End</label>
                                    <input type="time" class="form-control" id="working_hours_end" name="working_hours_end"
                                           value="<?php echo htmlspecialchars($deliveryman['working_hours_end']); ?>" required>
                                </div>
                            </div>
                            <button type="submit" name="update_availability" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
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
</script>
</body>
</html> 