<?php include 'header.php'; ?>
<?php
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
    'email' => '',
    'phone' => '',
    'address' => '',
    'vehicle_type' => '',
    'vehicle_number' => '',
    'password' => ''
];

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $vehicle_type = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_STRING);
        $vehicle_number = filter_input(INPUT_POST, 'vehicle_number', FILTER_SANITIZE_STRING);

        // Validate input
        if (empty($username) || empty($email) || empty($phone) || empty($address) || empty($vehicle_type) || empty($vehicle_number)) {
            $error = 'All fields are required';
        } else {
            // Check if username or email is already taken by another deliveryman
            $stmt = $conn->prepare('SELECT id FROM delivery_men WHERE (username = ? OR email = ?) AND id != ?');
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $error = 'Username or email is already taken';
            } else {
                // Update profile
                $stmt = $conn->prepare('
                    UPDATE delivery_men 
                    SET username = ?, email = ?, phone = ?, address = ?, vehicle_type = ?, vehicle_number = ?
                    WHERE id = ?
                ');
                if ($stmt->execute([$username, $email, $phone, $address, $vehicle_type, $vehicle_number, $_SESSION['user_id']])) {
                    $success = 'Profile updated successfully';
                    // Refresh deliveryman data
                    $stmt = $conn->prepare('SELECT * FROM delivery_men WHERE id = ?');
                    $stmt->execute([$_SESSION['user_id']]);
                    $deliveryman = $stmt->fetch(PDO::FETCH_ASSOC) ?: $deliveryman;
                } else {
                    $error = 'Failed to update profile';
                }
            }
        }
    } elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } else {
            // Verify current password
            if (password_verify($current_password, $deliveryman['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE delivery_men SET password = ? WHERE id = ?');
                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $success = 'Password updated successfully';
                } else {
                    $error = 'Failed to update password';
                }
            } else {
                $error = 'Current password is incorrect';
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
    <title>Profile - TriHealth Mart</title>
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
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
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
        <a class="nav-link active" href="profile.php">
            <i class="fas fa-user me-2"></i> Profile
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
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if ($deliveryman['image']): ?>
                            <img src="../<?php echo htmlspecialchars($deliveryman['image']); ?>" 
                                 alt="Profile Image" class="profile-image">
                        <?php else: ?>
                            <img src="../assets/images/default-profile.png" 
                                 alt="Default Profile Image" class="profile-image">
                        <?php endif; ?>
                        <h5 class="mb-1"><?php echo htmlspecialchars($deliveryman['username']); ?></h5>
                        <p class="text-muted mb-3">Deliveryman</p>
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                                <i class="fas fa-camera me-2"></i>Change Photo
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary w-100">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($deliveryman['username']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($deliveryman['email']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($deliveryman['phone']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                    <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                        <option value="Motorcycle" <?php echo $deliveryman['vehicle_type'] === 'Motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                        <option value="Car" <?php echo $deliveryman['vehicle_type'] === 'Car' ? 'selected' : ''; ?>>Car</option>
                                        <option value="Van" <?php echo $deliveryman['vehicle_type'] === 'Van' ? 'selected' : ''; ?>>Van</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" 
                                       value="<?php echo htmlspecialchars($deliveryman['vehicle_number']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($deliveryman['address']); ?></textarea>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Image Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Profile Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Choose Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                    </div>
                    <button type="submit" name="upload_image" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload
                    </button>
                </form>
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